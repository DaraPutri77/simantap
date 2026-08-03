<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Enums\VehicleLoanStatus;
use App\Enums\VehicleStatus;
use App\Http\Requests\ApproveVehicleLoanRequest;
use App\Http\Requests\CancelVehicleLoanRequest;
use App\Http\Requests\RejectVehicleLoanRequest;
use App\Http\Requests\StoreVehicleLoanRequest;
use App\Http\Requests\SubmitVehicleLoanRequest;
use App\Models\Vehicle;
use App\Models\VehicleLoan;
use App\Services\VehicleLoanService;
use App\Support\DisplayDateRange;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class VehicleLoanController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', VehicleLoan::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => [
                'nullable',
                Rule::in(VehicleLoanStatus::values()),
            ],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when(
                    $request->filled('from'),
                    'after_or_equal:from',
                ),
            ],
        ]);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $canViewAll = $actor->can(
            PermissionName::VehicleLoanViewAll->value,
        );
        $search = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $from = (string) ($filters['from'] ?? '');
        $until = (string) ($filters['until'] ?? '');
        $bounds = DisplayDateRange::utcBounds($from, $until);
        $baseQuery = VehicleLoan::query()
            ->when(
                ! $canViewAll,
                static fn (Builder $query): Builder => $query->where(
                    'borrower_id',
                    $actor->getKey(),
                ),
            );
        $vehicleLoans = (clone $baseQuery)
            ->with([
                'borrower:id,name,employee_number,work_unit',
                'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status',
            ])
            ->when(
                $search !== '',
                static function (Builder $query) use ($search): void {
                    $query->where(function (
                        Builder $nested,
                    ) use ($search): void {
                        $nested
                            ->where('loan_number', 'like', "%{$search}%")
                            ->orWhere(
                                'borrower_name_snapshot',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'employee_number_snapshot',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere('purpose', 'like', "%{$search}%")
                            ->orWhere('destination', 'like', "%{$search}%")
                            ->orWhereHas(
                                'vehicle',
                                static function (
                                    Builder $vehicleQuery,
                                ) use ($search): void {
                                    $vehicleQuery
                                        ->where(
                                            'vehicle_code',
                                            'like',
                                            "%{$search}%",
                                        )
                                        ->orWhere(
                                            'license_plate',
                                            'like',
                                            "%{$search}%",
                                        );
                                },
                            );
                    });
                },
            )
            ->when(
                $status !== '',
                static fn (Builder $query): Builder => $query->where(
                    'status',
                    $status,
                ),
            )
            ->when(
                $bounds['from'] !== null,
                static fn (Builder $query): Builder => $query->where(
                    'planned_start_at',
                    '>=',
                    $bounds['from'],
                ),
            )
            ->when(
                $bounds['until'] !== null,
                static fn (Builder $query): Builder => $query->where(
                    'planned_start_at',
                    '<=',
                    $bounds['until'],
                ),
            )
            ->latest('planned_start_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('vehicle-loans.index', [
            'vehicleLoans' => $vehicleLoans,
            'filters' => compact('search', 'status', 'from', 'until'),
            'statusOptions' => VehicleLoanStatus::options(),
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'waiting' => (clone $baseQuery)
                    ->whereIn('status', [
                        VehicleLoanStatus::Submitted->value,
                        VehicleLoanStatus::UnderReview->value,
                    ])
                    ->count(),
                'approved' => (clone $baseQuery)
                    ->whereIn('status', [
                        VehicleLoanStatus::Approved->value,
                        VehicleLoanStatus::ReadyForPickup->value,
                    ])
                    ->count(),
                'active' => (clone $baseQuery)
                    ->whereIn('status', [
                        VehicleLoanStatus::Borrowed->value,
                        VehicleLoanStatus::AwaitingReturnInspection->value,
                        VehicleLoanStatus::ReturnIssue->value,
                    ])
                    ->count(),
            ],
            'canViewAll' => $canViewAll,
            'canApprove' => $actor->can(
                PermissionName::VehicleLoanApprove->value,
            ),
            'routePrefix' => $this->routePrefix($request),
            'displayTimezone' => $this->displayTimezone(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', VehicleLoan::class);

        return view('vehicle-loans.create', [
            'vehicleLoan' => null,
            'vehicles' => $this->availableVehicles(),
            'displayTimezone' => $this->displayTimezone(),
        ]);
    }

    public function store(
        StoreVehicleLoanRequest $request,
        VehicleLoanService $service,
    ): RedirectResponse {
        Gate::authorize('create', VehicleLoan::class);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $vehicleLoan = $service->createDraft(
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('my.vehicle-loans.show', $vehicleLoan)
            ->with(
                'status',
                'Draft peminjaman berhasil dibuat. Periksa jadwal lalu bubuhkan tanda tangan untuk mengajukan.',
            );
    }

    public function show(
        Request $request,
        VehicleLoan $vehicleLoan,
        VehicleLoanService $service,
    ): View {
        Gate::authorize('view', $vehicleLoan);
        $this->loadLoan($vehicleLoan);

        return view('vehicle-loans.show', [
            'vehicleLoan' => $vehicleLoan,
            'routePrefix' => $this->routePrefix($request),
            'canManage' => $request->user()?->can(
                PermissionName::VehicleLoanApprove->value,
            ) === true,
            'submissionSignature' => $service->signatureDataUri(
                $vehicleLoan->submissionSignature(),
            ),
            'displayTimezone' => $this->displayTimezone(),
        ]);
    }

    public function edit(VehicleLoan $vehicleLoan): View
    {
        Gate::authorize('update', $vehicleLoan);

        return view('vehicle-loans.edit', [
            'vehicleLoan' => $vehicleLoan,
            'vehicles' => $this->availableVehicles([
                $vehicleLoan->vehicle_id,
            ]),
            'displayTimezone' => $this->displayTimezone(),
        ]);
    }

    public function update(
        StoreVehicleLoanRequest $request,
        VehicleLoan $vehicleLoan,
        VehicleLoanService $service,
    ): RedirectResponse {
        Gate::authorize('update', $vehicleLoan);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->updateDraft(
            $vehicleLoan,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('my.vehicle-loans.show', $vehicleLoan)
            ->with('status', 'Draft peminjaman berhasil diperbarui.');
    }

    public function submit(
        SubmitVehicleLoanRequest $request,
        VehicleLoan $vehicleLoan,
        VehicleLoanService $service,
    ): RedirectResponse {
        Gate::authorize('submit', $vehicleLoan);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->submit(
            $vehicleLoan,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('my.vehicle-loans.show', $vehicleLoan)
            ->with(
                'status',
                'Peminjaman berhasil diajukan dan menunggu pemeriksaan Administrator.',
            );
    }

    public function startReview(
        Request $request,
        VehicleLoan $vehicleLoan,
        VehicleLoanService $service,
    ): RedirectResponse {
        Gate::authorize('approve', $vehicleLoan);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->startReview($vehicleLoan, $actor, $request);

        return redirect()
            ->route('vehicle-loans.show', $vehicleLoan)
            ->with('status', 'Pemeriksaan peminjaman dimulai.');
    }

    public function approve(
        ApproveVehicleLoanRequest $request,
        VehicleLoan $vehicleLoan,
        VehicleLoanService $service,
    ): RedirectResponse {
        Gate::authorize('approve', $vehicleLoan);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->approve(
            $vehicleLoan,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('vehicle-loans.show', $vehicleLoan)
            ->with(
                'status',
                'Peminjaman disetujui dan jadwal kendaraan telah direservasi.',
            );
    }

    public function reject(
        RejectVehicleLoanRequest $request,
        VehicleLoan $vehicleLoan,
        VehicleLoanService $service,
    ): RedirectResponse {
        Gate::authorize('approve', $vehicleLoan);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->reject(
            $vehicleLoan,
            $request->validated('rejection_reason'),
            $actor,
            $request,
        );

        return redirect()
            ->route('vehicle-loans.show', $vehicleLoan)
            ->with('status', 'Peminjaman telah ditolak.');
    }

    public function cancel(
        CancelVehicleLoanRequest $request,
        VehicleLoan $vehicleLoan,
        VehicleLoanService $service,
    ): RedirectResponse {
        Gate::authorize('cancel', $vehicleLoan);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->cancel(
            $vehicleLoan,
            $request->validated('cancellation_reason'),
            $actor,
            $request,
        );

        return redirect()
            ->route(
                $this->routePrefix($request).'.show',
                $vehicleLoan,
            )
            ->with(
                'status',
                'Peminjaman berhasil dibatalkan. Reservasi kendaraan telah diperbarui.',
            );
    }

    public function downloadPdf(
        Request $request,
        VehicleLoan $vehicleLoan,
        VehicleLoanService $service,
    ): Response {
        Gate::authorize('view', $vehicleLoan);
        $this->loadLoan($vehicleLoan);

        $pdf = Pdf::loadView('vehicle-loans.pdf', [
            'vehicleLoan' => $vehicleLoan,
            'submissionSignature' => $service->signatureDataUri(
                $vehicleLoan->submissionSignature(),
            ),
            'institutionName' => (string) config(
                'simantap.institution.name',
                'Badan Pusat Statistik',
            ),
            'institutionShortName' => (string) config(
                'simantap.institution.short_name',
                'BPS',
            ),
            'displayTimezone' => $this->displayTimezone(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(
            str_replace('/', '-', $vehicleLoan->loan_number).'.pdf',
        );
    }

    /**
     * @param  list<int>  $includeIds
     * @return Collection<int, Vehicle>
     */
    private function availableVehicles(array $includeIds = []): Collection
    {
        return Vehicle::query()
            ->where(function (Builder $query) use ($includeIds): void {
                $query
                    ->where(function (Builder $active): void {
                        $active
                            ->where('is_active', true)
                            ->whereIn('status', [
                                VehicleStatus::Available->value,
                                VehicleStatus::Reserved->value,
                            ]);
                    });

                if ($includeIds !== []) {
                    $query->orWhereIn('id', $includeIds);
                }
            })
            ->orderBy('vehicle_code')
            ->get();
    }

    private function loadLoan(VehicleLoan $vehicleLoan): void
    {
        $vehicleLoan->load([
            'borrower:id,employee_number,name,phone,work_unit,position',
            'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status,registration_expiry_date,storage_location,responsible_person',
            'reviewer:id,name',
            'approver:id,name,position',
            'statusHistories.changer:id,name',
            'signatures.signer:id,name',
        ]);
    }

    private function routePrefix(Request $request): string
    {
        return $request->routeIs('my.vehicle-loans.*')
            ? 'my.vehicle-loans'
            : 'vehicle-loans';
    }

    private function displayTimezone(): string
    {
        return (string) config(
            'simantap.display_timezone',
            'Asia/Jakarta',
        );
    }
}

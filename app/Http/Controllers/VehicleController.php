<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Enums\VehicleStatus;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Vehicle;
use App\Services\DocumentSignatoryService;
use App\Services\DocumentVerificationService;
use App\Services\QrCodeService;
use App\Services\VehicleControlCardService;
use App\Services\VehicleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class VehicleController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => [
                'nullable',
                Rule::in(VehicleStatus::values()),
            ],
            'active' => ['nullable', 'in:active,inactive'],
            'registration' => [
                'nullable',
                'in:valid,expiring,expired,missing',
            ],
        ]);
        $canManage = $request->user()?->can(
            PermissionName::VehicleManage->value,
        ) === true;
        $search = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $active = (string) ($filters['active'] ?? '');
        $registration = (string) ($filters['registration'] ?? '');
        $timezone = (string) config(
            'simantap.display_timezone',
            'Asia/Jakarta',
        );
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $registrationWarningDate = $today->addDays(30);
        $baseQuery = Vehicle::query();

        if (! $canManage) {
            $baseQuery->where('is_active', true);
        }

        $query = (clone $baseQuery)
            ->when(
                $search !== '',
                static function (
                    Builder $vehicleQuery,
                ) use ($search): void {
                    $vehicleQuery->where(function (
                        Builder $nested,
                    ) use ($search): void {
                        $nested
                            ->where(
                                'vehicle_code',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'license_plate',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere('brand', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%")
                            ->orWhere('color', 'like', "%{$search}%")
                            ->orWhere(
                                'storage_location',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'responsible_person',
                                'like',
                                "%{$search}%",
                            );
                    });
                },
            )
            ->when(
                $status !== '',
                static fn (Builder $vehicleQuery): Builder => $vehicleQuery
                    ->where('status', $status),
            )
            ->when(
                $canManage && $active !== '',
                static fn (Builder $vehicleQuery): Builder => $vehicleQuery
                    ->where('is_active', $active === 'active'),
            )
            ->when(
                $registration === 'valid',
                static fn (Builder $vehicleQuery): Builder => $vehicleQuery
                    ->whereDate(
                        'registration_expiry_date',
                        '>',
                        $registrationWarningDate->toDateString(),
                    ),
            )
            ->when(
                $registration === 'expiring',
                static fn (Builder $vehicleQuery): Builder => $vehicleQuery
                    ->whereBetween('registration_expiry_date', [
                        $today->toDateString(),
                        $registrationWarningDate->toDateString(),
                    ]),
            )
            ->when(
                $registration === 'expired',
                static fn (Builder $vehicleQuery): Builder => $vehicleQuery
                    ->whereDate(
                        'registration_expiry_date',
                        '<',
                        $today->toDateString(),
                    ),
            )
            ->when(
                $registration === 'missing',
                static fn (Builder $vehicleQuery): Builder => $vehicleQuery
                    ->whereNull('registration_expiry_date'),
            );

        return view('vehicles.index', [
            'vehicles' => $query
                ->orderByDesc('is_active')
                ->orderBy('brand')
                ->orderBy('model')
                ->orderBy('license_plate')
                ->paginate(12)
                ->withQueryString(),
            'statusOptions' => VehicleStatus::cases(),
            'filters' => compact(
                'search',
                'status',
                'active',
                'registration',
            ),
            'canManage' => $canManage,
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'available' => (clone $baseQuery)
                    ->where('is_active', true)
                    ->where('status', VehicleStatus::Available->value)
                    ->count(),
                'unavailable' => (clone $baseQuery)
                    ->where('is_active', true)
                    ->where(
                        'status',
                        '!=',
                        VehicleStatus::Available->value,
                    )
                    ->count(),
                'registration_attention' => (clone $baseQuery)
                    ->where('is_active', true)
                    ->where(static function (
                        Builder $vehicleQuery,
                    ) use ($registrationWarningDate): void {
                        $vehicleQuery
                            ->whereNull('registration_expiry_date')
                            ->orWhereDate(
                                'registration_expiry_date',
                                '<=',
                                $registrationWarningDate->toDateString(),
                            );
                    })
                    ->count(),
            ],
            'today' => $today,
        ]);
    }

    public function create(): View
    {
        return view('vehicles.create', [
            'vehicle' => null,
            'statusOptions' => VehicleStatus::manuallyManagedCases(),
        ]);
    }

    public function store(
        StoreVehicleRequest $request,
        VehicleService $vehicleService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $vehicle = $vehicleService->createVehicle(
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('vehicles.show', $vehicle)
            ->with('status', 'Kendaraan berhasil ditambahkan.');
    }

    public function show(
        Request $request,
        Vehicle $vehicle,
        QrCodeService $qrCodes,
    ): View {
        $canManage = $request->user()?->can(
            PermissionName::VehicleManage->value,
        ) === true;

        abort_if(! $vehicle->is_active && ! $canManage, 404);

        $vehicle->loadCount([
            'vehicleLoans',
            'maintenanceRecords',
        ]);

        return view('vehicles.show', [
            'vehicle' => $vehicle,
            'canManage' => $canManage,
            'qrCodeSvg' => $qrCodes->svg($qrCodes->vehicleUrl($vehicle)),
            'qrTargetUrl' => $qrCodes->vehicleUrl($vehicle),
            'today' => CarbonImmutable::now(
                (string) config(
                    'simantap.display_timezone',
                    'Asia/Jakarta',
                ),
            )->startOfDay(),
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ]);
    }

    public function downloadControlCard(
        Request $request,
        Vehicle $vehicle,
        VehicleControlCardService $service,
        DocumentVerificationService $verificationService,
        QrCodeService $qrCodes,
        DocumentSignatoryService $signatories,
    ): Response {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless(
            $actor->can(PermissionName::VehicleManage->value),
            403,
        );

        $data = $service->build($vehicle);
        $documentSignatories = $signatories->for(
            'vehicle_control_card',
        );

        $documentVerification = $verificationService->issue(
            verifiable: $vehicle,
            documentType: 'vehicle_control_card',
            documentLabel: 'Kartu Kendali Kendaraan',
            documentReference: $vehicle->vehicle_code,
            payload: [
                ...$verificationService
                    ->vehicleControlCardPayload(
                        $vehicle,
                        $data,
                    ),
                'official_signatories' => $documentSignatories,
            ],
            actor: $actor,
            httpRequest: $request,
        );

        $data['documentVerification'] = $documentVerification;
        $data['verificationQrDataUri'] = $verificationService
            ->qrDataUri(
                $documentVerification,
                $qrCodes,
            );
        $data['documentSignatories'] = $documentSignatories;

        // PERUBAHAN: Menjadikan orientasi kertas menjadi landscape
        $pdf = Pdf::loadView(
            'vehicles.control-card-pdf',
            $data,
        )->setPaper('a4', 'landscape');

        $service->auditDownload(
            $vehicle,
            $actor,
            $data['recordCount'],
            $request,
        );

        return $pdf->download(
            $service->filename($vehicle),
        );
    }

    public function edit(Vehicle $vehicle): View
    {
        return view('vehicles.edit', [
            'vehicle' => $vehicle,
            'statusOptions' => $this->statusOptions($vehicle),
        ]);
    }

    public function update(
        UpdateVehicleRequest $request,
        Vehicle $vehicle,
        VehicleService $vehicleService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $vehicleService->updateVehicle(
            $vehicle,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('vehicles.show', $vehicle)
            ->with('status', 'Data kendaraan berhasil diperbarui.');
    }

    public function deactivate(
        Request $request,
        Vehicle $vehicle,
        VehicleService $vehicleService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $vehicleService->setVehicleActive(
            $vehicle,
            false,
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Kendaraan berhasil dinonaktifkan.',
        );
    }

    public function activate(
        Request $request,
        Vehicle $vehicle,
        VehicleService $vehicleService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $vehicleService->setVehicleActive(
            $vehicle,
            true,
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Kendaraan berhasil diaktifkan dan berstatus Tersedia.',
        );
    }

    // PENAMBAHAN: Fungsi Hapus Permanen Kendaraan
    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $vehicle->delete();
        return redirect()->route('vehicles.index')->with('status', 'Kendaraan berhasil dihapus permanen.');
    }

    /**
     * @return list<VehicleStatus>
     */
    private function statusOptions(Vehicle $vehicle): array
    {
        if (! $vehicle->is_active) {
            return [VehicleStatus::Inactive];
        }

        if ($vehicle->status->isTransactionControlled()) {
            return [$vehicle->status];
        }

        return VehicleStatus::manuallyManagedCases();
    }
}
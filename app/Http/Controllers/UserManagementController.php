<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Exports\EmployeeImportTemplateExport;
use App\Http\Requests\ImportEmployeesRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\InventoryRequest;
use App\Models\User;
use App\Models\VehicleLoan;
use App\Services\AccountService;
use App\Services\EmployeeImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $employeesQuery = $this->employeeQuery();

        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $workUnit = trim(
            (string) $request->query('work_unit', ''),
        );

        $employees = (clone $employeesQuery)
            ->with('creator:id,name')
            ->addSelect([
                'inventory_requests_count' => InventoryRequest::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn(
                        'inventory_requests.requested_by',
                        'users.id',
                    ),
                'vehicle_loans_count' => VehicleLoan::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn(
                        'vehicle_loans.borrower_id',
                        'users.id',
                    ),
            ])
            ->when(
                $search !== '',
                static function (
                    Builder $query,
                ) use ($search): void {
                    $query->where(function (
                        Builder $nestedQuery,
                    ) use ($search): void {
                        $nestedQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere(
                                'employee_number',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'email',
                                'like',
                                "%{$search}%",
                            );
                    });
                },
            )
            ->when(
                in_array(
                    $status,
                    array_map(
                        static fn (
                            AccountStatus $accountStatus,
                        ): string => $accountStatus->value,
                        AccountStatus::cases(),
                    ),
                    true,
                ),
                static fn (
                    Builder $query,
                ): Builder => $query->where('status', $status),
            )
            ->when(
                $workUnit !== '',
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'work_unit',
                    $workUnit,
                ),
            )
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('users.index', [
            'employees' => $employees,
            'search' => $search,
            'selectedStatus' => $status,
            'selectedWorkUnit' => $workUnit,
            'statusOptions' => AccountStatus::cases(),
            'workUnits' => (clone $employeesQuery)
                ->whereNotNull('work_unit')
                ->where('work_unit', '!=', '')
                ->distinct()
                ->orderBy('work_unit')
                ->pluck('work_unit'),
            'summary' => [
                'total' => (clone $employeesQuery)->count(),
                'active' => (clone $employeesQuery)
                    ->where(
                        'status',
                        AccountStatus::Active->value,
                    )
                    ->count(),
                'pending' => (clone $employeesQuery)
                    ->where(
                        'status',
                        AccountStatus::PendingActivation->value,
                    )
                    ->count(),
                'suspended' => (clone $employeesQuery)
                    ->where(
                        'status',
                        AccountStatus::Suspended->value,
                    )
                    ->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(
        StoreEmployeeRequest $request,
        AccountService $accountService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $employee = $accountService->createEmployee(
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('users.show', $employee)
            ->with(
                'status',
                'Akun pegawai berhasil dibuat dan tautan aktivasi telah dikirim.',
            );
    }

    public function show(User $user): View
    {
        $this->ensureManagedEmployee($user);

        $user->load('creator:id,name');
        $user->setAttribute(
            'inventory_requests_count',
            InventoryRequest::query()
                ->where('requested_by', $user->getKey())
                ->count(),
        );
        $user->setAttribute(
            'vehicle_loans_count',
            VehicleLoan::query()
                ->where('borrower_id', $user->getKey())
                ->count(),
        );

        return view('users.show', [
            'employee' => $user,
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ]);
    }

    public function edit(User $user): View
    {
        $this->ensureManagedEmployee($user);

        return view('users.edit', [
            'employee' => $user,
        ]);
    }

    public function update(
        UpdateEmployeeRequest $request,
        User $user,
        AccountService $accountService,
    ): RedirectResponse {
        $this->ensureManagedEmployee($user);

        $actor = $request->user();

        abort_if($actor === null, 401);

        $accountService->updateEmployee(
            $user,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('users.show', $user)
            ->with(
                'status',
                'Data pegawai berhasil diperbarui.',
            );
    }

    public function importForm(): View
    {
        return view('users.import');
    }

    public function downloadImportTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new EmployeeImportTemplateExport,
            'template-impor-pegawai-simantap.xlsx',
        );
    }

    public function import(
        ImportEmployeesRequest $request,
        EmployeeImportService $importService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $file = $request->file('employee_file');

        abort_if($file === null, 422);

        $importedCount = $importService->import(
            $file,
            $actor,
            $request,
        );

        return redirect()
            ->route('users.index')
            ->with(
                'status',
                "{$importedCount} akun pegawai berhasil diimpor dan tautan aktivasi telah dikirim.",
            );
    }

    public function resendActivation(
        Request $request,
        User $user,
        AccountService $accountService,
    ): RedirectResponse {
        $this->ensureManagedEmployee($user);

        $actor = $request->user();

        abort_if($actor === null, 401);

        $accountService->resendActivation(
            $user,
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Tautan aktivasi baru berhasil dikirim.',
        );
    }

    public function suspend(
        Request $request,
        User $user,
        AccountService $accountService,
    ): RedirectResponse {
        $this->ensureManagedEmployee($user);

        $actor = $request->user();

        abort_if($actor === null, 401);

        $accountService->suspend(
            $user,
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Akun pegawai berhasil dinonaktifkan.',
        );
    }

    public function reactivate(
        Request $request,
        User $user,
        AccountService $accountService,
    ): RedirectResponse {
        $this->ensureManagedEmployee($user);

        $actor = $request->user();

        abort_if($actor === null, 401);

        $accountService->reactivate(
            $user,
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Akun pegawai berhasil diaktifkan kembali.',
        );
    }

    public function sendPasswordReset(
        Request $request,
        User $user,
        AccountService $accountService,
    ): RedirectResponse {
        $this->ensureManagedEmployee($user);

        $actor = $request->user();

        abort_if($actor === null, 401);

        $accountService->sendPasswordReset(
            $user,
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Tautan reset kata sandi berhasil dikirim.',
        );
    }

    /**
     * @return Builder<User>
     */
    private function employeeQuery(): Builder
    {
        return User::query()->role(
            RoleName::Employee->value,
        );
    }

    private function ensureManagedEmployee(User $user): void
    {
        abort_unless(
            $user->roles()
                ->where('name', RoleName::Employee->value)
                ->where('guard_name', 'web')
                ->exists(),
            404,
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Enums\VehicleLoanStatus;
use App\Models\VehicleLoan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VehicleLoanApprovalController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', VehicleLoan::class);
        abort_unless(
            $request->user()?->can(
                PermissionName::VehicleLoanApprove->value,
            ) === true,
            403,
        );

        $actionableStatuses = [
            VehicleLoanStatus::Submitted->value,
            VehicleLoanStatus::UnderReview->value,
        ];
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'stage' => [
                'nullable',
                Rule::in($actionableStatuses),
            ],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $stage = (string) ($filters['stage'] ?? '');
        $baseQuery = VehicleLoan::query()
            ->whereIn('status', $actionableStatuses);
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
                $stage !== '',
                static fn (Builder $query): Builder => $query->where(
                    'status',
                    $stage,
                ),
            )
            ->orderByRaw(
                "CASE status
                    WHEN 'submitted' THEN 1
                    WHEN 'under_review' THEN 2
                    ELSE 3
                END",
            )
            ->oldest('planned_start_at')
            ->oldest('id')
            ->paginate(15)
            ->withQueryString();

        return view('vehicle-loans.approval-queue', [
            'vehicleLoans' => $vehicleLoans,
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'submitted' => (clone $baseQuery)
                    ->where(
                        'status',
                        VehicleLoanStatus::Submitted->value,
                    )
                    ->count(),
                'under_review' => (clone $baseQuery)
                    ->where(
                        'status',
                        VehicleLoanStatus::UnderReview->value,
                    )
                    ->count(),
            ],
            'stageOptions' => [
                VehicleLoanStatus::Submitted,
                VehicleLoanStatus::UnderReview,
            ],
            'filters' => compact('search', 'stage'),
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ]);
    }
}

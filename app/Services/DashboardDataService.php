<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\InventoryRequestStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\RoleName;
use App\Enums\VehicleLoanStatus;
use App\Enums\VehicleStatus;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestItem;
use App\Models\Item;
use App\Models\MaintenanceRecord;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class DashboardDataService
{
    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        return $user->hasRole(RoleName::Administrator->value)
            ? $this->administratorData()
            : $this->employeeData($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function administratorData(): array
    {
        $monthStart = $this->displayNow()->startOfMonth()->utc();

        return [
            'isAdmin' => true,
            'statistics' => [
                'items' => Item::query()
                    ->where('is_active', true)
                    ->count(),
                'stock' => (float) Item::query()
                    ->where('is_active', true)
                    ->sum('current_stock'),
                'low_stock' => Item::query()
                    ->where('is_active', true)
                    ->whereRaw(
                        '(current_stock - reserved_stock) <= minimum_stock',
                    )
                    ->count(),
                'pending_requests' => InventoryRequest::query()
                    ->whereIn('status', $this->pendingRequestStatuses())
                    ->count(),
                'requests_this_month' => InventoryRequest::query()
                    ->where('request_date', '>=', $monthStart)
                    ->whereNotIn('status', [
                        InventoryRequestStatus::Draft->value,
                        InventoryRequestStatus::Cancelled->value,
                    ])
                    ->count(),
                'available_vehicles' => Vehicle::query()
                    ->where('status', VehicleStatus::Available->value)
                    ->where('is_active', true)
                    ->count(),
                'borrowed_vehicles' => Vehicle::query()
                    ->where('status', VehicleStatus::Borrowed->value)
                    ->where('is_active', true)
                    ->count(),
                'maintenance_vehicles' => Vehicle::query()
                    ->where('status', VehicleStatus::Maintenance->value)
                    ->where('is_active', true)
                    ->count(),
                'active_employees' => User::query()
                    ->role(RoleName::Employee->value)
                    ->where('status', AccountStatus::Active->value)
                    ->count(),
            ],
            'inventoryChart' => $this->inventoryChart(),
            'vehicleChart' => $this->vehicleChart(),
            'recentRequests' => InventoryRequest::query()
                ->with('requester:id,name,work_unit')
                ->latest('request_date')
                ->limit(5)
                ->get(),
            'recentLoans' => VehicleLoan::query()
                ->with([
                    'borrower:id,name,work_unit',
                    'vehicle:id,brand,model,license_plate',
                ])
                ->latest('created_at')
                ->limit(5)
                ->get(),
            'lowStockItems' => Item::query()
                ->with('unit:id,name,symbol')
                ->where('is_active', true)
                ->whereRaw(
                    '(current_stock - reserved_stock) <= minimum_stock',
                )
                ->orderByRaw(
                    '(current_stock - reserved_stock) asc',
                )
                ->limit(5)
                ->get(),
            'overdueLoans' => VehicleLoan::query()
                ->with([
                    'borrower:id,name,work_unit',
                    'vehicle:id,brand,model,license_plate',
                ])
                ->where('status', VehicleLoanStatus::Borrowed->value)
                ->where(
                    fn ($query) => $query
                        ->whereNotNull('overdue_at')
                        ->orWhere('planned_end_at', '<', now()),
                )
                ->oldest('planned_end_at')
                ->limit(5)
                ->get(),
            'openMaintenance' => MaintenanceRecord::query()
                ->with('vehicle:id,brand,model,license_plate')
                ->whereIn('status', $this->openMaintenanceStatuses())
                ->oldest('reported_date')
                ->limit(5)
                ->get(),
            'mostRequestedItems' => InventoryRequestItem::query()
                ->select('item_id')
                ->selectRaw(
                    'SUM(requested_quantity) as total_requested',
                )
                ->with('item.unit:id,name,symbol')
                ->whereHas(
                    'inventoryRequest',
                    fn ($query) => $query->whereNotIn('status', [
                        InventoryRequestStatus::Draft->value,
                        InventoryRequestStatus::Cancelled->value,
                    ]),
                )
                ->groupBy('item_id')
                ->orderByDesc('total_requested')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeData(User $user): array
    {
        $requestQuery = InventoryRequest::query()
            ->where('requested_by', $user->id);
        $loanQuery = VehicleLoan::query()
            ->where('borrower_id', $user->id);
        $recentRequests = (clone $requestQuery)
            ->latest('request_date')
            ->limit(5)
            ->get();
        $recentLoans = (clone $loanQuery)
            ->with('vehicle:id,brand,model,license_plate')
            ->latest('created_at')
            ->limit(5)
            ->get();

        return [
            'isAdmin' => false,
            'statistics' => [
                'requests' => (clone $requestQuery)->count(),
                'pending_requests' => (clone $requestQuery)
                    ->whereIn('status', $this->pendingRequestStatuses())
                    ->count(),
                'approved_requests' => (clone $requestQuery)
                    ->whereIn('status', [
                        InventoryRequestStatus::Approved->value,
                        InventoryRequestStatus::PartiallyApproved->value,
                        InventoryRequestStatus::ReadyForDelivery->value,
                        InventoryRequestStatus::Delivered->value,
                        InventoryRequestStatus::Completed->value,
                    ])
                    ->count(),
                'vehicle_loans' => (clone $loanQuery)->count(),
                'active_loan' => (clone $loanQuery)
                    ->whereIn('status', $this->activeLoanStatuses())
                    ->exists(),
            ],
            'recentRequests' => $recentRequests,
            'recentLoans' => $recentLoans,
            'recentActivities' => $this->employeeActivities(
                $recentRequests,
                $recentLoans,
            ),
            'availableItems' => Item::query()
                ->with('unit:id,name,symbol')
                ->where('is_active', true)
                ->whereRaw(
                    '(current_stock - reserved_stock) > 0',
                )
                ->orderBy('name')
                ->limit(6)
                ->get(),
            'availableVehicles' => Vehicle::query()
                ->where('is_active', true)
                ->where('status', VehicleStatus::Available->value)
                ->orderBy('license_plate')
                ->limit(4)
                ->get(),
        ];
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     stock_in: list<float>,
     *     stock_out: list<float>,
     *     requests: list<int>
     * }
     */
    private function inventoryChart(): array
    {
        $months = $this->lastSixMonths();
        $start = $months->first()->copy()->startOfMonth()->utc();
        $end = $months->last()->copy()->endOfMonth()->utc();
        $movements = StockMovement::query()
            ->whereBetween('transaction_date', [$start, $end])
            ->get([
                'transaction_date',
                'quantity_in',
                'quantity_out',
            ]);
        $requests = InventoryRequest::query()
            ->whereBetween('request_date', [$start, $end])
            ->whereNotIn('status', [
                InventoryRequestStatus::Draft->value,
                InventoryRequestStatus::Cancelled->value,
            ])
            ->get(['request_date']);

        return [
            'labels' => $months
                ->map(
                    fn (CarbonInterface $month): string => $month
                        ->translatedFormat('M y'),
                )
                ->values()
                ->all(),
            'stock_in' => $months
                ->map(
                    fn (CarbonInterface $month): float => (float) $movements
                        ->filter(
                            fn (StockMovement $movement): bool => $movement
                                ->transaction_date
                                ->copy()
                                ->timezone($this->displayTimezone())
                                ->isSameMonth($month),
                        )
                        ->sum('quantity_in'),
                )
                ->values()
                ->all(),
            'stock_out' => $months
                ->map(
                    fn (CarbonInterface $month): float => (float) $movements
                        ->filter(
                            fn (StockMovement $movement): bool => $movement
                                ->transaction_date
                                ->copy()
                                ->timezone($this->displayTimezone())
                                ->isSameMonth($month),
                        )
                        ->sum('quantity_out'),
                )
                ->values()
                ->all(),
            'requests' => $months
                ->map(
                    fn (CarbonInterface $month): int => $requests
                        ->filter(
                            fn (InventoryRequest $inventoryRequest): bool => $inventoryRequest
                                ->request_date
                                ->copy()
                                ->timezone($this->displayTimezone())
                                ->isSameMonth($month),
                        )
                        ->count(),
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     loans: list<int>,
     *     maintenance: list<int>
     * }
     */
    private function vehicleChart(): array
    {
        $months = $this->lastSixMonths();
        $displayStart = $months->first()->copy()->startOfMonth();
        $displayEnd = $months->last()->copy()->endOfMonth();
        $start = $displayStart->copy()->utc();
        $end = $displayEnd->copy()->utc();
        $loans = VehicleLoan::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', [
                VehicleLoanStatus::Draft->value,
                VehicleLoanStatus::Cancelled->value,
            ])
            ->get(['created_at']);
        $maintenance = MaintenanceRecord::query()
            ->whereBetween('reported_date', [
                $displayStart->toDateString(),
                $displayEnd->toDateString(),
            ])
            ->get(['reported_date']);

        return [
            'labels' => $months
                ->map(
                    fn (CarbonInterface $month): string => $month
                        ->translatedFormat('M y'),
                )
                ->values()
                ->all(),
            'loans' => $months
                ->map(
                    fn (CarbonInterface $month): int => $loans
                        ->filter(
                            fn (VehicleLoan $loan): bool => $loan
                                ->created_at
                                ->copy()
                                ->timezone($this->displayTimezone())
                                ->isSameMonth($month),
                        )
                        ->count(),
                )
                ->values()
                ->all(),
            'maintenance' => $months
                ->map(
                    fn (CarbonInterface $month): int => $maintenance
                        ->filter(
                            fn (MaintenanceRecord $record): bool => $record
                                ->reported_date
                                ->isSameMonth($month),
                        )
                        ->count(),
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, InventoryRequest>  $requests
     * @param  Collection<int, VehicleLoan>  $loans
     * @return Collection<int, array{
     *     type: string,
     *     title: string,
     *     reference: string,
     *     status: string,
     *     occurred_at: CarbonInterface
     * }>
     */
    private function employeeActivities(
        Collection $requests,
        Collection $loans,
    ): Collection {
        return $requests
            ->map(
                fn (InventoryRequest $inventoryRequest): array => [
                    'type' => 'Permintaan barang',
                    'title' => $inventoryRequest->purpose,
                    'reference' => $inventoryRequest->request_number,
                    'status' => $inventoryRequest->status->label(),
                    'occurred_at' => $inventoryRequest->submitted_at
                        ?? $inventoryRequest->created_at,
                ],
            )
            ->merge(
                $loans->map(
                    fn (VehicleLoan $loan): array => [
                        'type' => 'Peminjaman motor',
                        'title' => $loan->vehicle === null
                            ? $loan->destination
                            : "{$loan->vehicle->brand} "
                                ."{$loan->vehicle->model}",
                        'reference' => $loan->loan_number,
                        'status' => $loan->status->label(),
                        'occurred_at' => $loan->created_at,
                    ],
                ),
            )
            ->sortByDesc('occurred_at')
            ->take(6)
            ->values();
    }

    /**
     * @return Collection<int, CarbonInterface>
     */
    private function lastSixMonths(): Collection
    {
        $firstMonth = $this->displayNow()
            ->startOfMonth()
            ->subMonths(5);

        return collect(range(0, 5))->map(
            fn (int $offset): CarbonInterface => $firstMonth
                ->copy()
                ->addMonths($offset),
        );
    }

    private function displayNow(): CarbonImmutable
    {
        return CarbonImmutable::now($this->displayTimezone());
    }

    private function displayTimezone(): string
    {
        return (string) config(
            'simantap.display_timezone',
            'Asia/Jakarta',
        );
    }

    /**
     * @return list<string>
     */
    private function pendingRequestStatuses(): array
    {
        return [
            InventoryRequestStatus::Submitted->value,
            InventoryRequestStatus::UnderReview->value,
        ];
    }

    /**
     * @return list<string>
     */
    private function activeLoanStatuses(): array
    {
        $statusValues = [
            'approved',
            'ready_for_pickup',
            'borrowed',
            'awaiting_return_inspection',
            'return_issue',
        ];

        return array_values(array_filter(array_map(
            static fn (string $status): ?string => VehicleLoanStatus::tryFrom(
                $status,
            )?->value,
            $statusValues,
        )));
    }

    /**
     * @return list<string>
     */
    private function openMaintenanceStatuses(): array
    {
        return [
            MaintenanceStatus::Reported->value,
            MaintenanceStatus::Approved->value,
            MaintenanceStatus::InProgress->value,
            MaintenanceStatus::FurtherActionRequired->value,
        ];
    }
}

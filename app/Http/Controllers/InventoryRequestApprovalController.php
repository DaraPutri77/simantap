<?php

namespace App\Http\Controllers;

use App\Enums\InventoryRequestStatus;
use App\Enums\PermissionName;
use App\Models\InventoryRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryRequestApprovalController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', InventoryRequest::class);
        abort_unless(
            $request->user()?->can(
                PermissionName::InventoryRequestApprove->value,
            ) === true,
            403,
        );

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'stage' => [
                'nullable',
                Rule::in([
                    InventoryRequestStatus::Submitted->value,
                    InventoryRequestStatus::UnderReview->value,
                    InventoryRequestStatus::WaitingStock->value,
                ]),
            ],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $stage = (string) ($filters['stage'] ?? '');
        $actionableStatuses = $this->actionableStatuses();
        $baseQuery = InventoryRequest::query()
            ->whereIn('status', $actionableStatuses);
        $queueQuery = (clone $baseQuery)
            ->with([
                'requester:id,name,employee_number,work_unit',
                'reviewer:id,name',
                'items:id,inventory_request_id,item_id,item_name_snapshot,unit_snapshot,requested_quantity',
                'items.item:id,current_stock,reserved_stock',
            ])
            ->when(
                $search !== '',
                static function (Builder $query) use ($search): void {
                    $query->where(function (
                        Builder $nested,
                    ) use ($search): void {
                        $nested
                            ->where(
                                'request_number',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'requester_name_snapshot',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'employee_number_snapshot',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'work_unit_snapshot',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'purpose',
                                'like',
                                "%{$search}%",
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
                    WHEN 'waiting_stock' THEN 3
                    ELSE 4
                END",
            )
            ->oldest('submitted_at')
            ->oldest('id');
        $inventoryRequests = $queueQuery
            ->paginate(15)
            ->withQueryString();

        return view('inventory-requests.approval-queue', [
            'inventoryRequests' => $inventoryRequests,
            'stockReadiness' => $this->stockReadiness(
                collect($inventoryRequests->items()),
            ),
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'submitted' => (clone $baseQuery)
                    ->where(
                        'status',
                        InventoryRequestStatus::Submitted->value,
                    )
                    ->count(),
                'under_review' => (clone $baseQuery)
                    ->where(
                        'status',
                        InventoryRequestStatus::UnderReview->value,
                    )
                    ->count(),
                'waiting_stock' => (clone $baseQuery)
                    ->where(
                        'status',
                        InventoryRequestStatus::WaitingStock->value,
                    )
                    ->count(),
            ],
            'stageOptions' => [
                InventoryRequestStatus::Submitted,
                InventoryRequestStatus::UnderReview,
                InventoryRequestStatus::WaitingStock,
            ],
            'filters' => compact('search', 'stage'),
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ]);
    }

    /**
     * @return list<string>
     */
    private function actionableStatuses(): array
    {
        return [
            InventoryRequestStatus::Submitted->value,
            InventoryRequestStatus::UnderReview->value,
            InventoryRequestStatus::WaitingStock->value,
        ];
    }

    /**
     * @param  Collection<int, InventoryRequest>  $inventoryRequests
     * @return array<int, array{
     *     label: string,
     *     detail: string,
     *     tone: string,
     *     sufficient: int,
     *     total: int
     * }>
     */
    private function stockReadiness(Collection $inventoryRequests): array
    {
        $readiness = [];

        foreach ($inventoryRequests as $inventoryRequest) {
            $total = $inventoryRequest->items->count();
            $sufficient = 0;
            $available = 0;

            foreach ($inventoryRequest->items as $line) {
                $requestedQuantity = (float) $line->requested_quantity;
                $availableQuantity = max(
                    0,
                    (float) ($line->item?->available_stock ?? 0),
                );

                if ($availableQuantity > 0) {
                    $available++;
                }

                if (
                    $requestedQuantity > 0
                    && $availableQuantity >= $requestedQuantity
                ) {
                    $sufficient++;
                }
            }

            if ($total > 0 && $sufficient === $total) {
                $label = 'Stok Cukup';
                $tone = 'bg-emerald-100 text-emerald-900 ring-emerald-300';
            } elseif ($available > 0) {
                $label = 'Tersedia Sebagian';
                $tone = 'bg-amber-100 text-amber-950 ring-amber-300';
            } else {
                $label = 'Stok Belum Cukup';
                $tone = 'bg-red-100 text-red-900 ring-red-300';
            }

            $readiness[$inventoryRequest->getKey()] = [
                'label' => $label,
                'detail' => sprintf(
                    '%d dari %d jenis barang mencukupi',
                    $sufficient,
                    $total,
                ),
                'tone' => $tone,
                'sufficient' => $sufficient,
                'total' => $total,
            ];
        }

        return $readiness;
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\InventoryReceipt;
use App\Models\InventoryRequest;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Support\DisplayDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'item' => ['nullable', 'integer'],
            'type' => [
                'nullable',
                'in:'.implode(',', StockMovementType::values()),
            ],
            'direction' => ['nullable', 'in:inbound,outbound'],
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
        $search = trim((string) ($filters['q'] ?? ''));
        $itemId = (int) ($filters['item'] ?? 0);
        $type = (string) ($filters['type'] ?? '');
        $direction = (string) ($filters['direction'] ?? '');
        $from = (string) ($filters['from'] ?? '');
        $until = (string) ($filters['until'] ?? '');
        $bounds = DisplayDateRange::utcBounds($from, $until);
        $query = StockMovement::query()
            ->with([
                'item' => static function (BelongsTo $itemQuery): void {
                    $itemQuery
                        ->withTrashed()
                        ->with(['category', 'unit']);
                },
                'creator' => static function (BelongsTo $creatorQuery): void {
                    $creatorQuery
                        ->withTrashed()
                        ->select(['id', 'name', 'employee_number']);
                },
            ])
            ->when(
                $search !== '',
                static function (
                    Builder $movementQuery,
                ) use ($search): void {
                    $movementQuery->where(function (
                        Builder $nested,
                    ) use ($search): void {
                        $nested
                            ->where(
                                'transaction_number',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'reference_number',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhereHas(
                                'item',
                                static fn (Builder $itemQuery): Builder => $itemQuery
                                    ->where(function (
                                        Builder $itemSearch,
                                    ) use ($search): void {
                                        $itemSearch
                                            ->where(
                                                'name',
                                                'like',
                                                "%{$search}%",
                                            )
                                            ->orWhere(
                                                'item_code',
                                                'like',
                                                "%{$search}%",
                                            );
                                    }),
                            )
                            ->orWhereHas(
                                'creator',
                                static fn (Builder $creatorQuery): Builder => $creatorQuery
                                    ->withTrashed()
                                    ->where('name', 'like', "%{$search}%"),
                            );
                    });
                },
            )
            ->when(
                $itemId > 0,
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('item_id', $itemId),
            )
            ->when(
                $type !== '',
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('movement_type', $type),
            )
            ->when(
                $direction === 'inbound',
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('quantity_in', '>', 0),
            )
            ->when(
                $direction === 'outbound',
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('quantity_out', '>', 0),
            )
            ->when(
                $bounds['from'] !== null,
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where(
                        'transaction_date',
                        '>=',
                        $bounds['from'],
                    ),
            )
            ->when(
                $bounds['until'] !== null,
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where(
                        'transaction_date',
                        '<=',
                        $bounds['until'],
                    ),
            );
        $summaryQuery = clone $query;

        return view('inventory.stock.index', [
            'movements' => $query
                ->latest('transaction_date')
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'items' => Item::query()
                ->withTrashed()
                ->whereHas('stockMovements')
                ->orderBy('name')
                ->get([
                    'id',
                    'item_code',
                    'name',
                    'is_active',
                    'deleted_at',
                ]),
            'typeOptions' => StockMovementType::cases(),
            'filters' => compact(
                'search',
                'itemId',
                'type',
                'direction',
                'from',
                'until',
            ),
            'summary' => [
                'transactions' => (clone $summaryQuery)->count(),
                'inbound' => (clone $summaryQuery)
                    ->where('quantity_in', '>', 0)
                    ->count(),
                'outbound' => (clone $summaryQuery)
                    ->where('quantity_out', '>', 0)
                    ->count(),
                'items' => (clone $summaryQuery)
                    ->distinct()
                    ->count('item_id'),
                'imbalances' => (clone $summaryQuery)
                    ->whereRaw(
                        'ABS(stock_after - (stock_before + quantity_in - quantity_out)) >= ?',
                        [0.005],
                    )
                    ->count(),
            ],
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ]);
    }

    public function show(StockMovement $stockMovement): View
    {
        $stockMovement->load([
            'item.category',
            'item.unit',
            'creator:id,name,employee_number',
            'reference',
        ]);
        $stockMovement->loadMorph('reference', [
            InventoryReceipt::class => [
                'creator:id,name',
                'postedBy:id,name',
            ],
            InventoryRequest::class => [
                'requester:id,name,employee_number',
                'deliverer:id,name',
            ],
            Item::class => ['category', 'unit'],
            StockAdjustment::class => [
                'creator:id,name',
                'postedBy:id,name',
            ],
        ]);

        $previousMovement = $this->adjacentMovement(
            $stockMovement,
            previous: true,
        );
        $nextMovement = $this->adjacentMovement(
            $stockMovement,
            previous: false,
        );
        $previousIsConsistent = $previousMovement === null
            || abs(
                (float) $previousMovement->stock_after
                    - (float) $stockMovement->stock_before,
            ) < 0.005;
        $nextIsConsistent = $nextMovement === null
            || abs(
                (float) $stockMovement->stock_after
                    - (float) $nextMovement->stock_before,
            ) < 0.005;

        return view('inventory.stock.show', [
            'movement' => $stockMovement,
            'source' => $this->sourceDetails($stockMovement),
            'previousMovement' => $previousMovement,
            'nextMovement' => $nextMovement,
            'integrity' => [
                'formula' => $stockMovement->hasConsistentBalance(),
                'previous' => $previousIsConsistent,
                'next' => $nextIsConsistent,
                'overall' => $stockMovement->hasConsistentBalance()
                    && $previousIsConsistent
                    && $nextIsConsistent,
            ],
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ]);
    }

    private function adjacentMovement(
        StockMovement $movement,
        bool $previous,
    ): ?StockMovement {
        $comparison = $previous ? '<' : '>';
        $direction = $previous ? 'desc' : 'asc';

        return StockMovement::query()
            ->where('item_id', $movement->item_id)
            ->where(function (Builder $query) use (
                $comparison,
                $movement,
            ): void {
                $query
                    ->where(
                        'transaction_date',
                        $comparison,
                        $movement->transaction_date,
                    )
                    ->orWhere(function (Builder $sameTime) use (
                        $comparison,
                        $movement,
                    ): void {
                        $sameTime
                            ->where(
                                'transaction_date',
                                $movement->transaction_date,
                            )
                            ->where('id', $comparison, $movement->getKey());
                    });
            })
            ->orderBy('transaction_date', $direction)
            ->orderBy('id', $direction)
            ->first();
    }

    /**
     * @return array{
     *     type: string,
     *     number: string,
     *     url: string|null,
     *     employee: string|null,
     *     status: string|null
     * }
     */
    private function sourceDetails(StockMovement $movement): array
    {
        $reference = $movement->reference;

        return match (true) {
            $reference instanceof InventoryReceipt => [
                'type' => 'Barang Masuk',
                'number' => $reference->receipt_number,
                'url' => $reference->trashed()
                    ? null
                    : route('inventory-receipts.show', $reference),
                'employee' => null,
                'status' => $reference->status->label(),
            ],
            $reference instanceof InventoryRequest => [
                'type' => 'Permintaan Barang',
                'number' => $reference->request_number,
                'url' => $reference->trashed()
                    ? null
                    : route('inventory-requests.show', $reference),
                'employee' => $reference->requester_name_snapshot,
                'status' => $reference->status->label(),
            ],
            $reference instanceof StockAdjustment => [
                'type' => 'Penyesuaian Stok',
                'number' => $reference->adjustment_number,
                'url' => $reference->trashed()
                    ? null
                    : route('stock-adjustments.show', $reference),
                'employee' => null,
                'status' => $reference->status->label(),
            ],
            $reference instanceof Item => [
                'type' => 'Stok Awal',
                'number' => $reference->item_code,
                'url' => $reference->trashed()
                    ? null
                    : route('items.show', $reference),
                'employee' => null,
                'status' => $reference->is_active && ! $reference->trashed()
                    ? 'Aktif'
                    : 'Nonaktif',
            ],
            default => [
                'type' => 'Sumber Sistem',
                'number' => $movement->reference_number
                    ?: $movement->transaction_number,
                'url' => null,
                'employee' => null,
                'status' => null,
            ],
        };
    }
}

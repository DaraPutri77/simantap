<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\Item;
use App\Models\StockMovement;
use App\Support\DisplayDateRange;
use Illuminate\Database\Eloquent\Builder;
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
        $from = (string) ($filters['from'] ?? '');
        $until = (string) ($filters['until'] ?? '');
        $bounds = DisplayDateRange::utcBounds($from, $until);
        $query = StockMovement::query()
            ->with(['item.unit', 'creator:id,name'])
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
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'item_code', 'name']),
            'typeOptions' => StockMovementType::cases(),
            'filters' => compact(
                'search',
                'itemId',
                'type',
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
            ],
            'displayTimezone' => 'Asia/Jakarta',
        ]);
    }
}

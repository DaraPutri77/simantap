<?php

namespace App\Http\Controllers;

use App\Enums\StockAdjustmentStatus;
use App\Http\Requests\CancelInventoryTransactionRequest;
use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Services\InventoryService;
use App\Support\DisplayDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => [
                'nullable',
                'in:'.implode(
                    ',',
                    array_map(
                        static fn (
                            StockAdjustmentStatus $status,
                        ): string => $status->value,
                        StockAdjustmentStatus::cases(),
                    ),
                ),
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
        $status = (string) ($filters['status'] ?? '');
        $from = (string) ($filters['from'] ?? '');
        $until = (string) ($filters['until'] ?? '');
        $bounds = DisplayDateRange::utcBounds($from, $until);

        $adjustments = StockAdjustment::query()
            ->with('creator:id,name')
            ->withCount('items')
            ->withSum('items', 'difference_quantity')
            ->when(
                $search !== '',
                static fn (Builder $query): Builder => $query
                    ->where(function (
                        Builder $nested,
                    ) use ($search): void {
                        $nested
                            ->where(
                                'adjustment_number',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'reason',
                                'like',
                                "%{$search}%",
                            );
                    }),
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
                    'adjustment_date',
                    '>=',
                    $bounds['from'],
                ),
            )
            ->when(
                $bounds['until'] !== null,
                static fn (Builder $query): Builder => $query->where(
                    'adjustment_date',
                    '<=',
                    $bounds['until'],
                ),
            )
            ->latest('adjustment_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('inventory.adjustments.index', [
            'adjustments' => $adjustments,
            'filters' => compact(
                'search',
                'status',
                'from',
                'until',
            ),
            'statusOptions' => StockAdjustmentStatus::cases(),
            'displayTimezone' => $this->displayTimezone(),
        ]);
    }

    public function create(): View
    {
        return view('inventory.adjustments.create', [
            'adjustment' => null,
            'items' => $this->activeItems(),
        ]);
    }

    public function store(
        StoreStockAdjustmentRequest $request,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $adjustment = $inventoryService->createAdjustment(
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('stock-adjustments.show', $adjustment)
            ->with(
                'status',
                'Draft penyesuaian berhasil dibuat. Periksa selisih sebelum posting.',
            );
    }

    public function show(StockAdjustment $stockAdjustment): View
    {
        return view('inventory.adjustments.show', [
            'adjustment' => $stockAdjustment->load([
                'items.item.unit',
                'creator:id,name',
                'postedBy:id,name',
                'cancelledBy:id,name',
            ]),
            'displayTimezone' => $this->displayTimezone(),
        ]);
    }

    public function edit(StockAdjustment $stockAdjustment): View
    {
        abort_unless(
            $stockAdjustment->status
                === StockAdjustmentStatus::Draft,
            404,
        );

        return view('inventory.adjustments.edit', [
            'adjustment' => $stockAdjustment->load(
                'items.item.unit',
            ),
            'items' => $this->activeItems(),
        ]);
    }

    public function update(
        StoreStockAdjustmentRequest $request,
        StockAdjustment $stockAdjustment,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $inventoryService->updateAdjustment(
            $stockAdjustment,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('stock-adjustments.show', $stockAdjustment)
            ->with(
                'status',
                'Draft penyesuaian stok berhasil diperbarui.',
            );
    }

    public function post(
        Request $request,
        StockAdjustment $stockAdjustment,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $inventoryService->postAdjustment(
            $stockAdjustment,
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Penyesuaian berhasil diposting. Stok fisik dan kartu stok telah diperbarui.',
        );
    }

    public function cancel(
        CancelInventoryTransactionRequest $request,
        StockAdjustment $stockAdjustment,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $inventoryService->cancelAdjustment(
            $stockAdjustment,
            $request->validated('cancellation_reason'),
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Draft penyesuaian stok berhasil dibatalkan.',
        );
    }

    private function displayTimezone(): string
    {
        return (string) config(
            'simantap.display_timezone',
            'Asia/Jakarta',
        );
    }

    /**
     * @return Collection<int, Item>
     */
    private function activeItems()
    {
        return Item::query()
            ->with('unit')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}

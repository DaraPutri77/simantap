<?php

namespace App\Http\Controllers;

use App\Enums\InventoryReceiptStatus;
use App\Http\Requests\CancelInventoryTransactionRequest;
use App\Http\Requests\StoreInventoryReceiptRequest;
use App\Models\InventoryReceipt;
use App\Models\Item;
use App\Services\InventoryService;
use App\Support\DisplayDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryReceiptController extends Controller
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
                            InventoryReceiptStatus $status,
                        ): string => $status->value,
                        InventoryReceiptStatus::cases(),
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

        $receipts = InventoryReceipt::query()
            ->with('creator:id,name')
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->when(
                $search !== '',
                static fn (Builder $query): Builder => $query
                    ->where(function (
                        Builder $nested,
                    ) use ($search): void {
                        $nested
                            ->where(
                                'receipt_number',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'source',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'reference_number',
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
                    'receipt_date',
                    '>=',
                    $bounds['from'],
                ),
            )
            ->when(
                $bounds['until'] !== null,
                static fn (Builder $query): Builder => $query->where(
                    'receipt_date',
                    '<=',
                    $bounds['until'],
                ),
            )
            ->latest('receipt_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('inventory.receipts.index', [
            'receipts' => $receipts,
            'filters' => compact(
                'search',
                'status',
                'from',
                'until',
            ),
            'statusOptions' => InventoryReceiptStatus::cases(),
            'displayTimezone' => 'Asia/Jakarta',
        ]);
    }

    public function create(): View
    {
        return view('inventory.receipts.create', [
            'receipt' => null,
            'items' => $this->activeItems(),
        ]);
    }

    public function store(
        StoreInventoryReceiptRequest $request,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $receipt = $inventoryService->createReceipt(
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('inventory-receipts.show', $receipt)
            ->with(
                'status',
                'Draft barang masuk berhasil dibuat. Periksa kembali lalu lakukan posting.',
            );
    }

    public function show(InventoryReceipt $inventoryReceipt): View
    {
        return view('inventory.receipts.show', [
            'receipt' => $inventoryReceipt->load([
                'items.item.unit',
                'creator:id,name',
                'postedBy:id,name',
                'cancelledBy:id,name',
            ]),
            'displayTimezone' => 'Asia/Jakarta',
        ]);
    }

    public function edit(InventoryReceipt $inventoryReceipt): View
    {
        abort_unless(
            $inventoryReceipt->status
                === InventoryReceiptStatus::Draft,
            404,
        );

        return view('inventory.receipts.edit', [
            'receipt' => $inventoryReceipt->load('items.item.unit'),
            'items' => $this->activeItems(),
        ]);
    }

    public function update(
        StoreInventoryReceiptRequest $request,
        InventoryReceipt $inventoryReceipt,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $inventoryService->updateReceipt(
            $inventoryReceipt,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('inventory-receipts.show', $inventoryReceipt)
            ->with('status', 'Draft barang masuk berhasil diperbarui.');
    }

    public function post(
        Request $request,
        InventoryReceipt $inventoryReceipt,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $inventoryService->postReceipt(
            $inventoryReceipt,
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Barang masuk berhasil diposting. Stok dan kartu stok telah diperbarui.',
        );
    }

    public function cancel(
        CancelInventoryTransactionRequest $request,
        InventoryReceipt $inventoryReceipt,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $inventoryService->cancelReceipt(
            $inventoryReceipt,
            $request->validated('cancellation_reason'),
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Draft barang masuk berhasil dibatalkan.',
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

<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'integer'],
            'status' => [
                'nullable',
                'in:active,inactive',
            ],
            'stock' => [
                'nullable',
                'in:available,low,out',
            ],
        ]);
        $canManage = $request->user()?->can(
            PermissionName::ItemManage->value,
        ) === true;
        $search = trim((string) ($filters['q'] ?? ''));
        $categoryId = (int) ($filters['category'] ?? 0);
        $status = (string) ($filters['status'] ?? '');
        $stock = (string) ($filters['stock'] ?? '');
        $baseQuery = Item::query();

        if (! $canManage) {
            $baseQuery->where('is_active', true);
        }

        $items = (clone $baseQuery)
            ->with(['category', 'unit'])
            ->when(
                $search !== '',
                static function (
                    Builder $query,
                ) use ($search): void {
                    $query->where(function (
                        Builder $nested,
                    ) use ($search): void {
                        $nested
                            ->where('item_code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere(
                                'storage_location',
                                'like',
                                "%{$search}%",
                            );
                    });
                },
            )
            ->when(
                $categoryId > 0,
                static fn (Builder $query): Builder => $query
                    ->where('category_id', $categoryId),
            )
            ->when(
                $canManage && $status !== '',
                static fn (Builder $query): Builder => $query->where(
                    'is_active',
                    $status === 'active',
                ),
            )
            ->when(
                $stock === 'available',
                static fn (Builder $query): Builder => $query->whereRaw(
                    '(current_stock - reserved_stock) > minimum_stock',
                ),
            )
            ->when(
                $stock === 'low',
                static fn (Builder $query): Builder => $query
                    ->whereRaw(
                        '(current_stock - reserved_stock) <= minimum_stock',
                    )
                    ->whereRaw(
                        '(current_stock - reserved_stock) > 0',
                    ),
            )
            ->when(
                $stock === 'out',
                static fn (Builder $query): Builder => $query->whereRaw(
                    '(current_stock - reserved_stock) <= 0',
                ),
            )
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
        $activeQuery = Item::query()->where('is_active', true);

        return view('inventory.items.index', [
            'items' => $items,
            'categories' => ItemCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'q' => $search,
                'category' => $categoryId,
                'status' => $status,
                'stock' => $stock,
            ],
            'canManage' => $canManage,
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'active' => Item::query()
                    ->where('is_active', true)
                    ->count(),
                'low' => (clone $activeQuery)
                    ->whereRaw(
                        '(current_stock - reserved_stock) <= minimum_stock',
                    )
                    ->count(),
                'inactive' => Item::query()
                    ->where('is_active', false)
                    ->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('inventory.items.create', [
            ...$this->formOptions(),
            'item' => null,
        ]);
    }

    public function store(
        StoreItemRequest $request,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $item = $inventoryService->createItem(
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('items.show', $item)
            ->with(
                'status',
                'Barang berhasil dibuat dan stok awal telah dicatat pada kartu stok.',
            );
    }

    public function show(Request $request, Item $item): View
    {
        $canManage = $request->user()?->can(
            PermissionName::ItemManage->value,
        ) === true;

        abort_if(! $item->is_active && ! $canManage, 404);

        $item->load(['category', 'unit']);

        return view('inventory.items.show', [
            'item' => $item,
            'canManage' => $canManage,
            'movements' => StockMovement::query()
                ->with('creator:id,name')
                ->where('item_id', $item->getKey())
                ->latest('transaction_date')
                ->latest('id')
                ->limit(10)
                ->get(),
            'displayTimezone' => 'Asia/Jakarta',
        ]);
    }

    public function edit(Item $item): View
    {
        return view('inventory.items.edit', [
            ...$this->formOptions($item),
            'item' => $item->load(['category', 'unit']),
        ]);
    }

    public function update(
        UpdateItemRequest $request,
        Item $item,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $inventoryService->updateItem(
            $item,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('items.show', $item)
            ->with('status', 'Data barang berhasil diperbarui.');
    }

    public function deactivate(
        Request $request,
        Item $item,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $inventoryService->setItemActive(
            $item,
            false,
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Barang berhasil dinonaktifkan.',
        );
    }

    public function activate(
        Request $request,
        Item $item,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $inventoryService->setItemActive(
            $item,
            true,
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Barang berhasil diaktifkan kembali.',
        );
    }

    /**
     * @return array{
     *     categories: Collection<int, ItemCategory>,
     *     units: Collection<int, Unit>
     * }
     */
    private function formOptions(?Item $item = null): array
    {
        return [
            'categories' => ItemCategory::query()
                ->where(function (Builder $query) use ($item): void {
                    $query->where('is_active', true);

                    if ($item !== null) {
                        $query->orWhereKey($item->category_id);
                    }
                })
                ->orderBy('name')
                ->get(),
            'units' => Unit::query()
                ->where(function (Builder $query) use ($item): void {
                    $query->where('is_active', true);

                    if ($item !== null) {
                        $query->orWhereKey($item->unit_id);
                    }
                })
                ->orderBy('name')
                ->get(),
        ];
    }
}

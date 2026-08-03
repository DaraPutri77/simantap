<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemCategoryRequest;
use App\Models\ItemCategory;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ItemCategoryController extends Controller
{
    public function index(): View
    {
        return view('inventory.categories.index', [
            'categories' => ItemCategory::query()
                ->withCount('items')
                ->orderBy('name')
                ->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('inventory.categories.form', [
            'category' => null,
        ]);
    }

    public function store(
        StoreItemCategoryRequest $request,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $inventoryService->createCategory(
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('item-categories.index')
            ->with('status', 'Kategori barang berhasil ditambahkan.');
    }

    public function edit(ItemCategory $itemCategory): View
    {
        return view('inventory.categories.form', [
            'category' => $itemCategory,
        ]);
    }

    public function update(
        StoreItemCategoryRequest $request,
        ItemCategory $itemCategory,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $inventoryService->updateCategory(
            $itemCategory,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('item-categories.index')
            ->with('status', 'Kategori barang berhasil diperbarui.');
    }
}

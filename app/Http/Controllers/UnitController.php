<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnitRequest;
use App\Models\Unit;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        return view('inventory.units.index', [
            'units' => Unit::query()
                ->withCount('items')
                ->orderBy('name')
                ->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('inventory.units.form', [
            'unit' => null,
        ]);
    }

    public function store(
        StoreUnitRequest $request,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $inventoryService->createUnit(
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('units.index')
            ->with('status', 'Satuan barang berhasil ditambahkan.');
    }

    public function edit(Unit $unit): View
    {
        return view('inventory.units.form', [
            'unit' => $unit,
        ]);
    }

    public function update(
        StoreUnitRequest $request,
        Unit $unit,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $actor = $request->user();

        abort_if($actor === null, 401);

        $inventoryService->updateUnit(
            $unit,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('units.index')
            ->with('status', 'Satuan barang berhasil diperbarui.');
    }
}

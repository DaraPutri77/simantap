@php
    $canManageItems = auth()->user()?->can(
        \App\Enums\PermissionName::ItemManage->value,
    ) === true;
    $canManageStock = auth()->user()?->can(
        \App\Enums\PermissionName::StockManage->value,
    ) === true;
    $tabs = [
        [
            'label' => 'Daftar Barang',
            'route' => 'items.index',
            'active' => ['items.*'],
            'visible' => true,
        ],
        [
            'label' => 'Kategori',
            'route' => 'item-categories.index',
            'active' => ['item-categories.*'],
            'visible' => $canManageItems,
        ],
        [
            'label' => 'Satuan',
            'route' => 'units.index',
            'active' => ['units.*'],
            'visible' => $canManageItems,
        ],
        [
            'label' => 'Barang Masuk',
            'route' => 'inventory-receipts.index',
            'active' => ['inventory-receipts.*'],
            'visible' => $canManageStock,
        ],
        [
            'label' => 'Penyesuaian',
            'route' => 'stock-adjustments.index',
            'active' => ['stock-adjustments.*'],
            'visible' => $canManageStock,
        ],
        [
            'label' => 'Kartu Stok',
            'route' => 'stock.index',
            'active' => ['stock.*'],
            'visible' => $canManageStock,
        ],
    ];
@endphp

<nav
    class="mb-6 overflow-x-auto rounded-2xl border border-slate-300 bg-white p-1.5 shadow-sm"
    aria-label="Submenu persediaan"
>
    <div class="flex min-w-max gap-1">
        @foreach ($tabs as $tab)
            @if ($tab['visible'])
                @php
                    $isActive = collect($tab['active'])->contains(
                        fn (string $pattern): bool => request()
                            ->routeIs($pattern),
                    );
                @endphp

                <a
                    href="{{ route($tab['route']) }}"
                    class="rounded-xl px-4 py-2.5 text-xs font-extrabold transition {{ $isActive
                        ? 'bg-slate-950 text-white shadow-sm'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}"
                    @if ($isActive)
                        aria-current="page"
                    @endif
                >
                    {{ $tab['label'] }}
                </a>
            @endif
        @endforeach
    </div>
</nav>

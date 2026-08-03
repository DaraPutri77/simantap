<x-layouts.app
    title="Detail Barang"
    header="Detail Barang"
    eyebrow="Persediaan"
>
    @include('inventory.partials.tabs')

    @if ($errors->any())
        <div class="alert-danger mb-6" role="alert">
            <div>
                <p class="font-extrabold">Tindakan belum dapat diproses.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-[0_24px_65px_rgba(15,23,42,.18)] sm:p-8">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_16%,rgba(14,165,233,.24),transparent_30%),radial-gradient(circle_at_88%_84%,rgba(37,99,235,.18),transparent_28%)]"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center">
            <div class="grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-3xl bg-white/10 text-2xl font-black ring-1 ring-white/10">
                @if ($item->image_path)
                    <img
                        src="{{ asset('storage/'.$item->image_path) }}"
                        alt="Foto {{ $item->name }}"
                        class="h-full w-full object-cover"
                    >
                @else
                    {{ mb_strtoupper(mb_substr($item->name, 0, 1)) }}
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-extrabold uppercase tracking-[.16em] text-sky-300">
                        {{ $item->item_code }}
                    </p>
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold ring-1 ring-inset {{ $item->is_active
                        ? 'bg-emerald-400/15 text-emerald-200 ring-emerald-300/20'
                        : 'bg-slate-400/15 text-slate-300 ring-slate-300/20' }}">
                        {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
                <h1 class="mt-2 break-words text-3xl font-black tracking-tight sm:text-4xl">
                    {{ $item->name }}
                </h1>
                <p class="mt-2 text-sm font-medium text-slate-300">
                    {{ $item->category->name }} · {{ $item->unit->name }}
                    ({{ $item->unit->symbol }})
                </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:flex">
                <a href="{{ route('items.index') }}" class="button-secondary-dark">
                    Kembali
                </a>
                @if ($canManage)
                    <a href="{{ route('items.edit', $item) }}" class="button-primary-inline">
                        Edit Barang
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-6">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Ringkasan Stok</h2>
                        <p class="panel-subtitle">Saldo berasal dari ledger transaksi</p>
                    </div>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-3 sm:p-6">
                    @foreach ([
                        ['label' => 'Stok Fisik', 'value' => $item->current_stock, 'tone' => 'text-sky-700 bg-sky-50'],
                        ['label' => 'Direservasi', 'value' => $item->reserved_stock, 'tone' => 'text-amber-700 bg-amber-50'],
                        ['label' => 'Tersedia', 'value' => $item->available_stock, 'tone' => 'text-emerald-700 bg-emerald-50'],
                    ] as $stock)
                        <div class="rounded-2xl p-4 {{ $stock['tone'] }}">
                            <p class="text-[10px] font-black uppercase tracking-[.12em] opacity-70">
                                {{ $stock['label'] }}
                            </p>
                            <p class="mt-2 text-2xl font-black">
                                {{ number_format((float) $stock['value'], 2, ',', '.') }}
                            </p>
                            <p class="mt-1 text-xs font-bold">{{ $item->unit->symbol }}</p>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Pergerakan Terakhir</h2>
                        <p class="panel-subtitle">Maksimal 10 transaksi terbaru</p>
                    </div>
                    @if ($canManage)
                        <a
                            href="{{ route('stock.index', ['item' => $item->id]) }}"
                            class="text-xs font-extrabold text-sky-700 hover:text-sky-900"
                        >
                            Kartu Lengkap
                        </a>
                    @endif
                </div>
                @if ($movements->isEmpty())
                    <div class="empty-state">Belum ada pergerakan stok.</div>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($movements as $movement)
                            <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-black text-slate-900">
                                        {{ $movement->movement_type->label() }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $movement->transaction_number }}
                                        ·
                                        {{ $movement->transaction_date->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }}
                                        WIB
                                    </p>
                                </div>
                                <div class="text-left sm:text-right">
                                    <p class="font-black {{ (float) $movement->quantity_in > 0
                                        ? 'text-emerald-700'
                                        : 'text-red-700' }}">
                                        {{ (float) $movement->quantity_in > 0 ? '+' : '-' }}
                                        {{ number_format(
                                            (float) ((float) $movement->quantity_in > 0
                                                ? $movement->quantity_in
                                                : $movement->quantity_out),
                                            2,
                                            ',',
                                            '.',
                                        ) }}
                                        {{ $item->unit->symbol }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Saldo {{ number_format((float) $movement->stock_after, 2, ',', '.') }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>
        </div>

        <aside class="space-y-6">
            <article class="panel p-5 sm:p-6">
                <h2 class="panel-title">Informasi Barang</h2>
                <dl class="mt-5 space-y-4 text-sm">
                    @foreach ([
                        'Kategori' => $item->category->name,
                        'Satuan' => $item->unit->name.' ('.$item->unit->symbol.')',
                        'Stok minimum' => number_format((float) $item->minimum_stock, 2, ',', '.').' '.$item->unit->symbol,
                        'Lokasi' => $item->storage_location ?: 'Belum diisi',
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs font-bold text-slate-500">{{ $label }}</dt>
                            <dd class="mt-1 font-extrabold text-slate-800">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($item->description)
                    <div class="mt-5 border-t border-slate-100 pt-5">
                        <p class="text-xs font-bold text-slate-500">Keterangan</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ $item->description }}
                        </p>
                    </div>
                @endif
            </article>

            @if ($canManage)
                <article class="panel p-5 sm:p-6">
                    <h2 class="panel-title">Status Master</h2>
                    <p class="mt-2 text-xs leading-5 text-slate-500">
                        Menonaktifkan barang tidak menghapus stok atau riwayat transaksi.
                    </p>
                    @if ($item->is_active)
                        <form
                            method="POST"
                            action="{{ route('items.deactivate', $item) }}"
                            class="mt-4"
                            data-confirm-message="Nonaktifkan barang ini?"
                        >
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="secondary-button text-red-700">
                                Nonaktifkan Barang
                            </button>
                        </form>
                    @else
                        <form
                            method="POST"
                            action="{{ route('items.activate', $item) }}"
                            class="mt-4"
                        >
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="button-primary-inline w-full">
                                Aktifkan Kembali
                            </button>
                        </form>
                    @endif
                </article>
            @endif
        </aside>
    </section>
</x-layouts.app>

<x-layouts.app
    title="Kartu Stok"
    header="Kartu Stok"
    eyebrow="Persediaan"
>
    @include('inventory.partials.tabs')

    <section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="eyebrow">Ledger Persediaan</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Kartu Stok
            </h1>
            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-500">
                Seluruh pergerakan terbentuk otomatis dari transaksi sumber.
                Setiap catatan menyimpan saldo sebelum dan sesudah, petugas,
                serta jejak dokumen yang dapat ditelusuri.
            </p>
        </div>
        <div class="rounded-2xl bg-slate-950 px-4 py-3 text-xs font-bold leading-5 text-slate-300 sm:max-w-sm">
            Ledger bersifat permanen: tidak dapat ditambah, diubah, atau
            dihapus secara manual.
        </div>
    </section>

    <section class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-5">
        @foreach ([
            ['label' => 'Transaksi', 'value' => $summary['transactions'], 'tone' => 'bg-sky-50 text-sky-700 ring-sky-100'],
            ['label' => 'Pergerakan Masuk', 'value' => $summary['inbound'], 'tone' => 'bg-emerald-50 text-emerald-700 ring-emerald-100'],
            ['label' => 'Pergerakan Keluar', 'value' => $summary['outbound'], 'tone' => 'bg-red-50 text-red-700 ring-red-100'],
            ['label' => 'Jenis Barang', 'value' => $summary['items'], 'tone' => 'bg-violet-50 text-violet-700 ring-violet-100'],
            [
                'label' => 'Validasi Saldo',
                'value' => $summary['imbalances'] === 0 ? 'Konsisten' : $summary['imbalances'].' anomali',
                'tone' => $summary['imbalances'] === 0
                    ? 'bg-teal-50 text-teal-700 ring-teal-100'
                    : 'bg-amber-50 text-amber-800 ring-amber-200',
            ],
        ] as $card)
            <article class="stat-card p-4 sm:p-5">
                <span class="inline-flex rounded-xl px-2.5 py-1 text-[10px] font-black uppercase tracking-[.12em] ring-1 ring-inset {{ $card['tone'] }}">
                    {{ $card['label'] }}
                </span>
                <p class="mt-4 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">
                    {{ is_numeric($card['value'])
                        ? number_format($card['value'], 0, ',', '.')
                        : $card['value'] }}
                </p>
            </article>
        @endforeach
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Riwayat Pergerakan</h2>
                <p class="panel-subtitle">{{ $movements->total() }} catatan ditemukan</p>
            </div>

            <a
                href="{{ route('stock.excel', [
                    'q' => $filters['search'] ?: null,
                    'item' => $filters['itemId'] ?: null,
                    'type' => $filters['type'] ?: null,
                    'direction' => $filters['direction'] ?: null,
                    'from' => $filters['from'] ?: null,
                    'until' => $filters['until'] ?: null,
                ]) }}"
                class="button-primary-inline"
            >
                Unduh Excel Sesuai Filter
            </a>
        </div>

        <form
            method="GET"
            action="{{ route('stock.index') }}"
            class="inventory-filter-grid"
        >
            <div>
                <label for="q" class="form-label">Cari Transaksi</label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $filters['search'] }}"
                    class="form-input"
                    placeholder="Nomor, barang, atau keterangan"
                >
            </div>
            <div>
                <label for="item" class="form-label">Barang</label>
                <select id="item" name="item" class="form-input">
                    <option value="">Semua barang</option>
                    @foreach ($items as $item)
                        <option
                            value="{{ $item->id }}"
                            @selected($filters['itemId'] === $item->id)
                        >
                            {{ $item->item_code }} · {{ $item->name }}
                            @if (! $item->is_active || $item->trashed())
                                · Nonaktif
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="type" class="form-label">Jenis Transaksi</label>
                <select id="type" name="type" class="form-input">
                    <option value="">Semua jenis</option>
                    @foreach ($typeOptions as $typeOption)
                        <option
                            value="{{ $typeOption->value }}"
                            @selected($filters['type'] === $typeOption->value)
                        >
                            {{ $typeOption->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="direction" class="form-label">Arah Pergerakan</label>
                <select id="direction" name="direction" class="form-input">
                    <option value="">Semua arah</option>
                    <option value="inbound" @selected($filters['direction'] === 'inbound')>
                        Stok masuk
                    </option>
                    <option value="outbound" @selected($filters['direction'] === 'outbound')>
                        Stok keluar
                    </option>
                </select>
            </div>
            <div>
                <label for="from" class="form-label">Dari Tanggal</label>
                <input id="from" name="from" type="date" value="{{ $filters['from'] }}" class="form-input">
            </div>
            <div>
                <label for="until" class="form-label">Sampai Tanggal</label>
                <input id="until" name="until" type="date" value="{{ $filters['until'] }}" class="form-input">
            </div>
            <div class="inventory-filter-actions">
                <a href="{{ route('stock.index') }}" class="secondary-button">Reset</a>
                <button type="submit" class="button-primary-inline">Terapkan</button>
            </div>
        </form>

        @if ($selectedItem)
            <div class="mx-5 mb-5 rounded-2xl border border-sky-100 bg-sky-50 p-4 sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[.14em] text-sky-700">
                            Mode Kartu Stok Per Barang
                        </p>
                        <h3 class="mt-2 text-lg font-black text-slate-950">
                            {{ $selectedItem->item_code }} · {{ $selectedItem->name }}
                        </h3>
                        <p class="mt-1 text-xs font-medium leading-5 text-slate-600">
                            {{ $selectedItem->category?->name ?: 'Kategori tidak tersedia' }}
                            ·
                            {{ $selectedItem->unit?->symbol ?: 'Satuan tidak tersedia' }}
                            @if ($filters['from'] || $filters['until'])
                                · periode mengikuti filter tanggal
                            @endif
                        </p>
                        <p class="mt-2 max-w-3xl text-xs leading-5 text-slate-500">
                            Kartu formal selalu memuat seluruh pergerakan masuk dan keluar
                            untuk barang ini pada periode yang dipilih agar kesinambungan
                            saldo tidak terputus. Filter jenis dan arah di halaman ledger
                            tetap digunakan untuk penelusuran transaksi.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a
                            href="{{ route('stock.card', [
                                'item' => $selectedItem,
                                'from' => $filters['from'] ?: null,
                                'until' => $filters['until'] ?: null,
                            ]) }}"
                            class="secondary-button"
                        >
                            Buka Kartu Barang
                        </a>

                        <a
                            href="{{ route('stock.card.pdf', [
                                'item' => $selectedItem,
                                'from' => $filters['from'] ?: null,
                                'until' => $filters['until'] ?: null,
                            ]) }}"
                            class="secondary-button"
                        >
                            Unduh PDF
                        </a>

                        <a
                            href="{{ route('stock.card.excel', [
                                'item' => $selectedItem,
                                'from' => $filters['from'] ?: null,
                                'until' => $filters['until'] ?: null,
                            ]) }}"
                            class="button-primary-inline"
                        >
                            Unduh Excel Kartu
                        </a>
                    </div>
                </div>
            </div>
        @endif

        @if ($movements->isEmpty())
            <div class="empty-state">
                Belum ada pergerakan stok yang sesuai dengan filter.
            </div>
        @else
            <div class="hidden overflow-x-auto lg:block">
                <table class="data-table min-w-[1180px]">
                    <thead>
                        <tr>
                            <th>Tanggal & Nomor</th>
                            <th>Barang</th>
                            <th>Jenis</th>
                            <th>Stok Awal</th>
                            <th>Masuk</th>
                            <th>Keluar</th>
                            <th>Stok Akhir</th>
                            <th>Petugas</th>
                            <th>Sumber</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($movements as $movement)
                            <tr>
                                <td>
                                    <p class="font-extrabold text-slate-900">
                                        {{ $movement->transaction_date->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $movement->transaction_number }} · WIB
                                    </p>
                                </td>
                                <td>
                                    <a
                                        href="{{ route('items.show', $movement->item) }}"
                                        class="font-extrabold text-slate-900 hover:text-sky-700"
                                    >
                                        {{ $movement->item->name }}
                                    </a>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $movement->item->item_code }}
                                    </p>
                                </td>
                                <td>
                                    @php
                                        $movementTone = match ($movement->movement_type) {
                                            \App\Enums\StockMovementType::InitialStock
                                                => 'bg-sky-50 text-sky-700 ring-sky-200',
                                            default => $movement->movement_type->isInbound()
                                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                                : 'bg-red-50 text-red-700 ring-red-200',
                                        };
                                    @endphp
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-extrabold ring-1 ring-inset {{ $movementTone }}"
                                        data-movement-tone="{{ $movement->movement_type === \App\Enums\StockMovementType::InitialStock
                                            ? 'initial'
                                            : ($movement->movement_type->isInbound() ? 'inbound' : 'outbound') }}"
                                    >
                                        {{ $movement->movement_type->label() }}
                                    </span>
                                </td>
                                <td>
                                    {{ number_format((float) $movement->stock_before, 2, ',', '.') }}
                                </td>
                                <td class="font-black text-emerald-700">
                                    {{ (float) $movement->quantity_in > 0
                                        ? '+'.number_format((float) $movement->quantity_in, 2, ',', '.')
                                        : '—' }}
                                </td>
                                <td class="font-black text-red-700">
                                    {{ (float) $movement->quantity_out > 0
                                        ? '-'.number_format((float) $movement->quantity_out, 2, ',', '.')
                                        : '—' }}
                                </td>
                                <td class="font-black text-slate-950">
                                    {{ number_format((float) $movement->stock_after, 2, ',', '.') }}
                                    {{ $movement->item->unit->symbol }}
                                </td>
                                <td>
                                    <p class="font-extrabold text-slate-900">
                                        {{ $movement->creator?->name ?: 'Akun tidak tersedia' }}
                                    </p>
                                    @if ($movement->creator?->employee_number)
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $movement->creator->employee_number }}
                                        </p>
                                    @endif
                                </td>
                                <td class="max-w-64">
                                    <p class="font-extrabold text-slate-800">
                                        {{ $movement->reference_number ?: $movement->transaction_number }}
                                    </p>
                                    <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">
                                        {{ $movement->description ?: 'Tanpa keterangan tambahan.' }}
                                    </p>
                                </td>
                                <td class="text-right">
                                    <a
                                        href="{{ route('stock.show', $movement) }}"
                                        class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-3 py-2 text-xs font-black text-white transition hover:bg-sky-700"
                                    >
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 lg:hidden">
                @foreach ($movements as $movement)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[10px] font-black uppercase tracking-[.12em] text-sky-600">
                                    {{ $movement->transaction_number }}
                                </p>
                                <h3 class="mt-1 truncate font-black text-slate-950">
                                    {{ $movement->item->name }}
                                </h3>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $movement->transaction_date->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }}
                                    WIB
                                </p>
                            </div>
                            @php
                                $movementTone = match ($movement->movement_type) {
                                    \App\Enums\StockMovementType::InitialStock
                                        => 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-200',
                                    default => $movement->movement_type->isInbound()
                                        ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200'
                                        : 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200',
                                };
                            @endphp
                            <span
                                class="rounded-full px-2.5 py-1 text-[10px] font-extrabold {{ $movementTone }}"
                                data-movement-tone="{{ $movement->movement_type === \App\Enums\StockMovementType::InitialStock
                                    ? 'initial'
                                    : ($movement->movement_type->isInbound() ? 'inbound' : 'outbound') }}"
                            >
                                {{ $movement->movement_type->label() }}
                            </span>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2 rounded-2xl bg-slate-50 p-4 text-center">
                            <div>
                                <p class="text-[9px] font-bold uppercase text-slate-500">Awal</p>
                                <p class="mt-1 text-xs font-black text-slate-800">
                                    {{ number_format((float) $movement->stock_before, 2, ',', '.') }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[9px] font-bold uppercase text-slate-500">
                                    {{ (float) $movement->quantity_in > 0 ? 'Masuk' : 'Keluar' }}
                                </p>
                                <p class="mt-1 text-xs font-black {{ (float) $movement->quantity_in > 0
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
                                </p>
                            </div>
                            <div>
                                <p class="text-[9px] font-bold uppercase text-slate-500">Akhir</p>
                                <p class="mt-1 text-xs font-black text-slate-800">
                                    {{ number_format((float) $movement->stock_after, 2, ',', '.') }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-end justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-[9px] font-black uppercase tracking-[.12em] text-slate-400">
                                    Sumber · Petugas
                                </p>
                                <p class="mt-1 truncate text-xs font-extrabold text-slate-700">
                                    {{ $movement->reference_number ?: $movement->transaction_number }}
                                    · {{ $movement->creator?->name ?: 'Akun tidak tersedia' }}
                                </p>
                            </div>
                            <a
                                href="{{ route('stock.show', $movement) }}"
                                class="shrink-0 text-xs font-black text-sky-700 hover:text-sky-900"
                            >
                                Lihat Detail
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-100 px-5 py-4">
                {{ $movements->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>

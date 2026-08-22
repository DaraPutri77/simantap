<x-layouts.app
    title="Kartu Stok {{ $item->name }}"
    header="Kartu Stok"
    eyebrow="Persediaan"
>
    @include('inventory.partials.tabs')

    <section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="eyebrow">Kartu Stok Per Barang</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Kartu Stok Persediaan
            </h1>
            <p class="mt-2 text-sm font-medium text-slate-500">
                {{ $item->item_code }} · {{ $item->name }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('stock.index', ['item' => $item->id]) }}"
                class="secondary-button"
            >
                Ledger Global
            </a>

            <a
                href="{{ route('stock.card.pdf', [
                    'item' => $item,
                    'from' => $from ?: null,
                    'until' => $until ?: null,
                ]) }}"
                class="secondary-button"
            >
                Unduh PDF
            </a>

            <a
                href="{{ route('stock.card.excel', [
                    'item' => $item,
                    'from' => $from ?: null,
                    'until' => $until ?: null,
                ]) }}"
                class="button-primary-inline"
            >
                Unduh Excel
            </a>
        </div>
    </section>

    <section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="stat-card p-4">
            <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-400">
                Kode Barang
            </p>
            <p class="mt-2 font-black text-slate-950">
                {{ $item->item_code }}
            </p>
        </article>

        <article class="stat-card p-4">
            <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-400">
                Nama Barang
            </p>
            <p class="mt-2 font-black text-slate-950">
                {{ $item->name }}
            </p>
        </article>

        <article class="stat-card p-4">
            <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-400">
                Kategori
            </p>
            <p class="mt-2 font-black text-slate-950">
                {{ $item->category?->name ?: 'Tidak tersedia' }}
            </p>
        </article>

        <article class="stat-card p-4">
            <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-400">
                Satuan
            </p>
            <p class="mt-2 font-black text-slate-950">
                {{ $item->unit?->symbol ?: 'Tidak tersedia' }}
            </p>
        </article>
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Periode Kartu</h2>
                <p class="panel-subtitle">
                    {{ $periodLabel }} · seluruh pergerakan barang dipertahankan
                </p>
            </div>
        </div>

        <form
            method="GET"
            action="{{ route('stock.card', $item) }}"
            class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto]"
        >
            <div>
                <label for="from" class="form-label">Dari Tanggal</label>
                <input
                    id="from"
                    name="from"
                    type="date"
                    value="{{ $from }}"
                    class="form-input"
                >
            </div>

            <div>
                <label for="until" class="form-label">Sampai Tanggal</label>
                <input
                    id="until"
                    name="until"
                    type="date"
                    value="{{ $until }}"
                    class="form-input"
                >
            </div>

            <div class="flex items-end gap-2">
                <a
                    href="{{ route('stock.card', $item) }}"
                    class="secondary-button"
                >
                    Reset
                </a>
                <button type="submit" class="button-primary-inline">
                    Terapkan Filter
                </button>
            </div>
        </form>
    </section>

    <section class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-5">
        @foreach ([
            [
                'label' => 'Saldo Awal Periode',
                'value' => number_format($openingBalance, 2, ',', '.'),
            ],
            [
                'label' => 'Total Masuk',
                'value' => number_format($totalIn, 2, ',', '.'),
            ],
            [
                'label' => 'Total Keluar',
                'value' => number_format($totalOut, 2, ',', '.'),
            ],
            [
                'label' => 'Saldo Akhir',
                'value' => number_format($closingBalance, 2, ',', '.'),
            ],
            [
                'label' => 'Validasi Saldo',
                'value' => $balanceConsistent ? 'Konsisten' : 'Perlu Audit',
            ],
        ] as $card)
            <article class="stat-card p-4">
                <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-400">
                    {{ $card['label'] }}
                </p>
                <p class="mt-2 text-lg font-black text-slate-950">
                    {{ $card['value'] }}
                </p>
            </article>
        @endforeach
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Pergerakan Stok</h2>
                <p class="panel-subtitle">
                    {{ $movements->count() }} transaksi · urut kronologis
                </p>
            </div>
        </div>

        @if ($movements->isEmpty())
            <div class="empty-state">
                Tidak ada pergerakan stok pada periode yang dipilih.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="data-table min-w-[900px]">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Nomor Dokumen</th>
                            <th>Uraian</th>
                            <th>Masuk</th>
                            <th>Keluar</th>
                            <th>Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($movements as $movement)
                            <tr>
                                <td>
                                    {{ $movement->transaction_date
                                        ->copy()
                                        ->timezone($displayTimezone)
                                        ->translatedFormat('d M Y, H:i') }}
                                    WIB
                                </td>
                                <td class="font-extrabold text-slate-900">
                                    {{ $movement->reference_number
                                        ?: $movement->transaction_number }}
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
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-extrabold ring-1 ring-inset {{ $movementTone }}"
                                        data-movement-tone="{{ $movement->movement_type === \App\Enums\StockMovementType::InitialStock
                                            ? 'initial'
                                            : ($movement->movement_type->isInbound() ? 'inbound' : 'outbound') }}"
                                    >
                                        {{ $movement->movement_type->label() }}
                                    </span>
                                    @if ($movement->description)
                                        <p class="mt-1 text-xs leading-5 text-slate-500">
                                            {{ $movement->description }}
                                        </p>
                                    @endif
                                </td>
                                <td class="font-black text-emerald-700">
                                    {{ (float) $movement->quantity_in > 0
                                        ? number_format((float) $movement->quantity_in, 2, ',', '.')
                                        : '—' }}
                                </td>
                                <td class="font-black text-red-700">
                                    {{ (float) $movement->quantity_out > 0
                                        ? number_format((float) $movement->quantity_out, 2, ',', '.')
                                        : '—' }}
                                </td>
                                <td class="font-black text-slate-950">
                                    {{ number_format((float) $movement->stock_after, 2, ',', '.') }}
                                    {{ $item->unit?->symbol }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
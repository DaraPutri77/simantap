<x-layouts.app
    title="Penyesuaian Stok"
    header="Penyesuaian Stok"
    eyebrow="Persediaan"
>
    @include('inventory.partials.tabs')

    <section class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Stock Opname</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Penyesuaian Stok
            </h1>
            <p class="mt-2 max-w-2xl text-sm font-medium leading-6 text-slate-500">
                Rekonsiliasi jumlah fisik dengan saldo sistem melalui transaksi
                yang tercatat dan dapat diaudit.
            </p>
        </div>
        <a href="{{ route('stock-adjustments.create') }}" class="button-primary-inline">
            Buat Penyesuaian
        </a>
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Daftar Penyesuaian</h2>
                <p class="panel-subtitle">{{ $adjustments->total() }} dokumen ditemukan</p>
            </div>
        </div>

        <form
            method="GET"
            action="{{ route('stock-adjustments.index') }}"
            class="inventory-filter-grid"
        >
            <div>
                <label for="q" class="form-label">Cari Dokumen</label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $filters['search'] }}"
                    class="form-input"
                    placeholder="Nomor atau alasan"
                >
            </div>
            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua status</option>
                    @foreach ($statusOptions as $statusOption)
                        <option
                            value="{{ $statusOption->value }}"
                            @selected($filters['status'] === $statusOption->value)
                        >
                            {{ $statusOption->label() }}
                        </option>
                    @endforeach
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
                <a href="{{ route('stock-adjustments.index') }}" class="secondary-button">Reset</a>
                <button type="submit" class="button-primary-inline">Terapkan</button>
            </div>
        </form>

        @if ($adjustments->isEmpty())
            <div class="empty-state">Belum ada penyesuaian stok.</div>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="data-table min-w-[820px]">
                    <thead>
                        <tr>
                            <th>Nomor & Tanggal</th>
                            <th>Alasan</th>
                            <th>Isi Dokumen</th>
                            <th>Status</th>
                            <th>Dibuat Oleh</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($adjustments as $adjustment)
                            @php
                                $statusTone = match ($adjustment->status) {
                                    \App\Enums\StockAdjustmentStatus::Draft => 'bg-amber-50 text-amber-700 ring-amber-100',
                                    \App\Enums\StockAdjustmentStatus::Posted => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
                                    \App\Enums\StockAdjustmentStatus::Cancelled => 'bg-red-50 text-red-700 ring-red-100',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <a
                                        href="{{ route('stock-adjustments.show', $adjustment) }}"
                                        class="font-extrabold text-slate-950 hover:text-sky-700"
                                    >
                                        {{ $adjustment->adjustment_number }}
                                    </a>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $adjustment->adjustment_date->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }}
                                        WIB
                                    </p>
                                </td>
                                <td class="max-w-70">
                                    <p class="line-clamp-2 text-sm text-slate-600">{{ $adjustment->reason }}</p>
                                </td>
                                <td>
                                    <p class="font-bold text-slate-800">{{ $adjustment->items_count }} barang</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Selisih neto
                                        {{ number_format((float) $adjustment->items_sum_difference_quantity, 2, ',', '.') }}
                                    </p>
                                </td>
                                <td>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-extrabold ring-1 ring-inset {{ $statusTone }}">
                                        {{ $adjustment->status->label() }}
                                    </span>
                                </td>
                                <td>{{ $adjustment->creator->name }}</td>
                                <td class="text-right">
                                    <a
                                        href="{{ route('stock-adjustments.show', $adjustment) }}"
                                        class="text-xs font-extrabold text-sky-700 hover:text-sky-900"
                                    >
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 md:hidden">
                @foreach ($adjustments as $adjustment)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[.12em] text-sky-600">
                                    {{ $adjustment->adjustment_number }}
                                </p>
                                <p class="mt-2 line-clamp-2 text-sm font-bold leading-5 text-slate-800">
                                    {{ $adjustment->reason }}
                                </p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-extrabold text-slate-700">
                                {{ $adjustment->status->label() }}
                            </span>
                        </div>
                        <p class="mt-4 text-xs text-slate-500">
                            {{ $adjustment->items_count }} barang ·
                            {{ $adjustment->adjustment_date->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }}
                            WIB
                        </p>
                        <a href="{{ route('stock-adjustments.show', $adjustment) }}" class="secondary-button mt-4">
                            Lihat Detail
                        </a>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-100 px-5 py-4">
                {{ $adjustments->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>

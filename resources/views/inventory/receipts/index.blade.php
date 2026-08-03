<x-layouts.app
    title="Barang Masuk"
    header="Barang Masuk"
    eyebrow="Persediaan"
>
    @include('inventory.partials.tabs')

    <section class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Transaksi Persediaan</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Barang Masuk
            </h1>
            <p class="mt-2 max-w-2xl text-sm font-medium leading-6 text-slate-500">
                Catat penerimaan, periksa draft, lalu posting untuk menambah stok
                dan membentuk kartu stok otomatis.
            </p>
        </div>
        <a href="{{ route('inventory-receipts.create') }}" class="button-primary-inline">
            Catat Barang Masuk
        </a>
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Daftar Penerimaan</h2>
                <p class="panel-subtitle">{{ $receipts->total() }} dokumen ditemukan</p>
            </div>
        </div>

        <form
            method="GET"
            action="{{ route('inventory-receipts.index') }}"
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
                    placeholder="Nomor, sumber, atau referensi"
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
                <a href="{{ route('inventory-receipts.index') }}" class="secondary-button">Reset</a>
                <button type="submit" class="button-primary-inline">Terapkan</button>
            </div>
        </form>

        @if ($receipts->isEmpty())
            <div class="empty-state">Belum ada transaksi barang masuk.</div>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="data-table min-w-[840px]">
                    <thead>
                        <tr>
                            <th>Nomor & Tanggal</th>
                            <th>Sumber</th>
                            <th>Isi Dokumen</th>
                            <th>Status</th>
                            <th>Dibuat Oleh</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($receipts as $receipt)
                            @php
                                $statusTone = match ($receipt->status) {
                                    \App\Enums\InventoryReceiptStatus::Draft => 'bg-amber-50 text-amber-700 ring-amber-100',
                                    \App\Enums\InventoryReceiptStatus::Posted => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
                                    \App\Enums\InventoryReceiptStatus::Cancelled => 'bg-red-50 text-red-700 ring-red-100',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <a
                                        href="{{ route('inventory-receipts.show', $receipt) }}"
                                        class="font-extrabold text-slate-950 hover:text-sky-700"
                                    >
                                        {{ $receipt->receipt_number }}
                                    </a>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $receipt->receipt_date->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }}
                                        WIB
                                    </p>
                                </td>
                                <td>
                                    <p class="font-bold text-slate-800">{{ $receipt->source }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $receipt->reference_number ?: 'Tanpa nomor referensi' }}
                                    </p>
                                </td>
                                <td>
                                    <p class="font-bold text-slate-800">{{ $receipt->items_count }} barang</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Total
                                        {{ number_format((float) $receipt->items_sum_quantity, 2, ',', '.') }}
                                        unit satuan
                                    </p>
                                </td>
                                <td>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-extrabold ring-1 ring-inset {{ $statusTone }}">
                                        {{ $receipt->status->label() }}
                                    </span>
                                </td>
                                <td>{{ $receipt->creator->name }}</td>
                                <td class="text-right">
                                    <a
                                        href="{{ route('inventory-receipts.show', $receipt) }}"
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
                @foreach ($receipts as $receipt)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[.12em] text-sky-600">
                                    {{ $receipt->receipt_number }}
                                </p>
                                <h3 class="mt-1 font-black text-slate-950">{{ $receipt->source }}</h3>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $receipt->receipt_date->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }}
                                    WIB
                                </p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-extrabold text-slate-700">
                                {{ $receipt->status->label() }}
                            </span>
                        </div>
                        <p class="mt-4 text-sm text-slate-500">
                            {{ $receipt->items_count }} barang · dibuat oleh
                            {{ $receipt->creator->name }}
                        </p>
                        <a href="{{ route('inventory-receipts.show', $receipt) }}" class="secondary-button mt-4">
                            Lihat Detail
                        </a>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-100 px-5 py-4">
                {{ $receipts->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>

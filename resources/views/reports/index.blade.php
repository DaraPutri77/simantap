<x-layouts.app
    title="Laporan PDF"
    header="Laporan PDF"
    eyebrow="Pusat Laporan"
>
    @php
        $movementReports = ['stock-in', 'stock-out', 'stock-card'];
        $requestReports = ['inventory-requests', 'inventory-usage'];
        $vehicleReports = ['vehicle-loans', 'vehicle-overdue'];
    @endphp

    <section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="eyebrow">Dokumen Operasional</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Pusat Laporan PDF
            </h1>
            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-500">
                Pilih jenis laporan, atur periode dan filter, lalu unduh dokumen
                resmi yang dibentuk langsung dari data transaksi SIMANTAP.
                Ekspor Excel berada pada tahap berikutnya.
            </p>
        </div>
        <div class="rounded-2xl bg-slate-950 px-4 py-3 text-xs font-bold leading-5 text-slate-300 sm:max-w-sm">
            Laporan menggunakan data sumber yang tersimpan di sistem. Tidak ada
            input ulang atau angka manual di dalam dokumen.
        </div>
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Parameter Laporan</h2>
                <p class="panel-subtitle">Filter diterapkan pada data PDF yang akan diunduh</p>
            </div>
            <span class="status-badge">PDF A4 Landscape</span>
        </div>

        <form method="GET" action="{{ route('reports.index') }}" class="inventory-filter-grid">
            <div class="sm:col-span-2">
                <label for="report" class="form-label">Jenis Laporan</label>
                <select id="report" name="report" class="form-input">
                    @foreach ($reportTypes as $key => $type)
                        <option value="{{ $key }}" @selected($filters['report'] === $key)>
                            {{ $type['label'] }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs font-medium leading-5 text-slate-500">
                    {{ $reportTypes[$filters['report']]['description'] }}
                </p>
            </div>
            <div class="sm:col-span-2">
                <label for="q" class="form-label">Pencarian</label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $filters['search'] }}"
                    class="form-input"
                    placeholder="Nomor, nama, kode, kendaraan, atau keperluan"
                >
            </div>
            @if (in_array($filters['report'], ['stock', ...$movementReports, 'inventory-usage'], true))
                <div>
                    <label for="item" class="form-label">Barang</label>
                    <select id="item" name="item" class="form-input">
                        <option value="">Semua barang</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}" @selected($filters['itemId'] === $item->id)>
                                {{ $item->item_code }} · {{ $item->name }}{{ $item->trashed() || ! $item->is_active ? ' · Nonaktif' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if (in_array($filters['report'], $movementReports, true))
                <div>
                    <label for="movement_type" class="form-label">Jenis Transaksi</label>
                    <select id="movement_type" name="movement_type" class="form-input">
                        <option value="">Semua jenis</option>
                        @foreach ($movementTypes as $key => $label)
                            <option value="{{ $key }}" @selected($filters['movementType'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if (in_array($filters['report'], [...$requestReports, ...$vehicleReports], true))
                <div>
                    <label for="work_unit" class="form-label">Unit Kerja</label>
                    <select id="work_unit" name="work_unit" class="form-input">
                        <option value="">Semua unit kerja</option>
                        @foreach ($workUnits as $workUnit)
                            <option value="{{ $workUnit }}" @selected($filters['workUnit'] === $workUnit)>{{ $workUnit }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if (count($statuses) > 0)
                <div>
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-input">
                        <option value="">Semua status</option>
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($filters['report'] !== 'stock')
                <div>
                    <label for="from" class="form-label">Dari Tanggal</label>
                    <input id="from" name="from" type="date" value="{{ $filters['from'] }}" class="form-input">
                </div>
                <div>
                    <label for="until" class="form-label">Sampai Tanggal</label>
                    <input id="until" name="until" type="date" value="{{ $filters['until'] }}" class="form-input">
                </div>
            @endif
            <div class="inventory-filter-actions">
                <a href="{{ route('reports.index') }}" class="secondary-button">Reset</a>
                <button type="submit" class="button-primary-inline">Terapkan</button>
            </div>
        </form>
    </section>

    <section class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
        @foreach ($summary as $card)
            <article class="stat-card p-4 sm:p-5">
                <span class="inline-flex rounded-xl bg-sky-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-[.12em] text-sky-700 ring-1 ring-inset ring-sky-100">
                    {{ $card['label'] }}
                </span>
                <p class="mt-4 break-words text-xl font-black tracking-tight text-slate-950 sm:text-2xl">
                    {{ $card['value'] }}
                </p>
            </article>
        @endforeach
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Pratinjau Data</h2>
                <p class="panel-subtitle">
                    Menampilkan maksimal 25 baris pertama · Periode: {{ $periodLabel }}
                </p>
            </div>
            <form method="GET" action="{{ route('reports.pdf', $filters['report']) }}" class="shrink-0">
                <input type="hidden" name="q" value="{{ $filters['search'] }}">
                <input type="hidden" name="item" value="{{ $filters['itemId'] ?: '' }}">
                <input type="hidden" name="movement_type" value="{{ $filters['movementType'] }}">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <input type="hidden" name="work_unit" value="{{ $filters['workUnit'] }}">
                <input type="hidden" name="from" value="{{ $filters['from'] }}">
                <input type="hidden" name="until" value="{{ $filters['until'] }}">
                <button type="submit" class="button-primary-inline">Unduh PDF</button>
            </form>
        </div>

        @if ($previewRows === [])
            <div class="empty-state">
                Belum ada data yang sesuai dengan parameter laporan.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="data-table min-w-[980px]">
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th>{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($previewRows as $row)
                            <tr>
                                @foreach ($columns as $column)
                                    <td class="max-w-72 align-top">
                                        <span class="break-words {{ in_array($column['key'], ['kode', 'nomor', 'referensi', 'permintaan'], true) ? 'font-mono text-xs font-bold' : 'font-medium' }}">
                                            {{ $row[$column['key']] ?? '-' }}
                                        </span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>

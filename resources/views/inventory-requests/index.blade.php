<x-layouts.app
    :title="$canViewAll ? 'Permintaan Barang' : 'Permintaan Saya'"
    :header="$canViewAll ? 'Permintaan Barang' : 'Permintaan Saya'"
    eyebrow="Persediaan"
>
    <section class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">
                {{ $canViewAll ? 'Kendali Permintaan' : 'Layanan Pegawai' }}
            </p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                {{ $canViewAll ? 'Permintaan Barang' : 'Permintaan Saya' }}
            </h1>
            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-600">
                @if ($canViewAll)
                    Periksa persediaan yang diminta, tetapkan jumlah persetujuan,
                    dan catat penyerahan tanpa memutus jejak kartu stok.
                @else
                    Ajukan beberapa barang dalam satu formulir, pantau statusnya,
                    lalu konfirmasi penerimaan setelah barang diserahkan.
                @endif
            </p>
        </div>

        @if ($canViewAll && $canApprove)
            <a
                href="{{ route('inventory-requests.approval-queue') }}"
                class="button-primary-inline"
            >
                Buka Antrean Approval
            </a>
        @elseif (! $canViewAll)
            <a
                href="{{ route('my.inventory-requests.create') }}"
                class="button-primary-inline"
            >
                Ajukan Permintaan
            </a>
        @endif
    </section>

    <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Total', 'value' => $summary['total'], 'tone' => 'bg-sky-100 text-sky-900 ring-sky-300'],
            ['label' => 'Dalam Proses', 'value' => $summary['waiting'], 'tone' => 'bg-amber-100 text-amber-900 ring-amber-300'],
            ['label' => 'Disetujui', 'value' => $summary['approved'], 'tone' => 'bg-emerald-100 text-emerald-900 ring-emerald-300'],
            ['label' => 'Selesai', 'value' => $summary['completed'], 'tone' => 'bg-teal-100 text-teal-900 ring-teal-300'],
        ] as $card)
            <article class="stat-card p-4 sm:p-5">
                <span class="inline-flex rounded-xl px-2.5 py-1 text-[10px] font-black uppercase tracking-[.12em] ring-1 ring-inset {{ $card['tone'] }}">
                    {{ $card['label'] }}
                </span>
                <p class="mt-4 text-3xl font-black tracking-tight text-slate-950">
                    {{ number_format($card['value'], 0, ',', '.') }}
                </p>
            </article>
        @endforeach
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Daftar Permintaan</h2>
                <p class="panel-subtitle">
                    {{ $inventoryRequests->total() }} permintaan ditemukan
                </p>
            </div>
        </div>

        <form
            method="GET"
            action="{{ route($routePrefix.'.index') }}"
            class="inventory-filter-grid"
        >
            <div>
                <label for="q" class="form-label">Cari Permintaan</label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $filters['search'] }}"
                    class="form-input"
                    placeholder="Nomor, pegawai, NIP, atau keperluan"
                >
            </div>
            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $statusOption)
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
                <input
                    id="from"
                    name="from"
                    type="date"
                    value="{{ $filters['from'] }}"
                    class="form-input"
                >
            </div>
            <div>
                <label for="until" class="form-label">Sampai Tanggal</label>
                <input
                    id="until"
                    name="until"
                    type="date"
                    value="{{ $filters['until'] }}"
                    class="form-input"
                >
            </div>
            <div class="inventory-filter-actions">
                <a
                    href="{{ route($routePrefix.'.index') }}"
                    class="secondary-button"
                >
                    Reset
                </a>
                <button type="submit" class="button-primary-inline">
                    Terapkan
                </button>
            </div>
        </form>

        @if ($inventoryRequests->isEmpty())
            <div class="empty-state">
                <p class="font-extrabold text-slate-700">
                    Belum ada permintaan yang sesuai.
                </p>
                <p class="mt-1">Ubah filter atau buat permintaan baru.</p>
            </div>
        @else
            <div class="hidden overflow-x-auto lg:block">
                <table class="data-table min-w-[980px]">
                    <thead>
                        <tr>
                            <th>Nomor & Tanggal</th>
                            @if ($canViewAll)
                                <th>Pegawai</th>
                            @endif
                            <th>Keperluan</th>
                            <th>Barang</th>
                            <th>Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inventoryRequests as $inventoryRequest)
                            <tr>
                                <td>
                                    <p class="font-extrabold text-slate-950">
                                        {{ $inventoryRequest->request_number }}
                                    </p>
                                    <p class="mt-1 text-xs font-semibold text-slate-600">
                                        {{ $inventoryRequest->request_date->copy()->timezone($displayTimezone)->translatedFormat('d F Y') }}
                                    </p>
                                </td>
                                @if ($canViewAll)
                                    <td>
                                        <p class="font-extrabold text-slate-900">
                                            {{ $inventoryRequest->requester_name_snapshot }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-600">
                                            {{ $inventoryRequest->employee_number_snapshot ?: 'Tanpa NIP' }}
                                            ·
                                            {{ $inventoryRequest->work_unit_snapshot ?: 'Unit belum diisi' }}
                                        </p>
                                    </td>
                                @endif
                                <td class="max-w-80">
                                    <p class="line-clamp-2 font-semibold leading-5 text-slate-800">
                                        {{ $inventoryRequest->purpose }}
                                    </p>
                                </td>
                                <td>
                                    <span class="font-black text-slate-950">
                                        {{ $inventoryRequest->items_count }}
                                    </span>
                                    <span class="text-slate-600">jenis</span>
                                </td>
                                <td>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-extrabold ring-1 ring-inset {{ $inventoryRequest->status->badgeClasses() }}">
                                        {{ $inventoryRequest->status->label() }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <a
                                        href="{{ route($routePrefix.'.show', $inventoryRequest) }}"
                                        class="text-xs font-extrabold text-sky-800 hover:text-sky-950"
                                    >
                                        Lihat Detail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-200 lg:hidden">
                @foreach ($inventoryRequests as $inventoryRequest)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-[10px] font-black uppercase tracking-[.12em] text-sky-700">
                                    {{ $inventoryRequest->request_number }}
                                </p>
                                <h3 class="mt-1 line-clamp-2 font-black text-slate-950">
                                    {{ $inventoryRequest->purpose }}
                                </h3>
                                @if ($canViewAll)
                                    <p class="mt-1 text-xs font-semibold text-slate-600">
                                        {{ $inventoryRequest->requester_name_snapshot }}
                                    </p>
                                @endif
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-extrabold ring-1 ring-inset {{ $inventoryRequest->status->badgeClasses() }}">
                                {{ $inventoryRequest->status->label() }}
                            </span>
                        </div>
                        <div class="mt-4 flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div>
                                <p class="text-[10px] font-bold uppercase text-slate-600">
                                    Tanggal
                                </p>
                                <p class="mt-1 text-xs font-black text-slate-900">
                                    {{ $inventoryRequest->request_date->copy()->timezone($displayTimezone)->translatedFormat('d M Y') }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] font-bold uppercase text-slate-600">
                                    Barang
                                </p>
                                <p class="mt-1 text-xs font-black text-slate-900">
                                    {{ $inventoryRequest->items_count }} jenis
                                </p>
                            </div>
                        </div>
                        <a
                            href="{{ route($routePrefix.'.show', $inventoryRequest) }}"
                            class="secondary-button mt-4"
                        >
                            Lihat Detail
                        </a>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-200 px-5 py-4">
                {{ $inventoryRequests->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>

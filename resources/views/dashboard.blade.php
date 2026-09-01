@php
    $user = auth()->user();
    $displayTimezone = (string) config(
        'simantap.display_timezone',
        'Asia/Jakarta',
    );
    $displayNow = now()->timezone($displayTimezone);

    if ($isAdmin) {
        $dashboardStatistics = [
            [
                'label' => 'Jenis Barang',
                'value' => number_format($statistics['items'], 0, ',', '.'),
                'note' => 'Barang aktif',
                'accent' => 'from-sky-400 to-blue-500',
            ],
            [
                'label' => 'Total Stok',
                'value' => number_format($statistics['stock'], 2, ',', '.'),
                'note' => 'Seluruh satuan',
                'accent' => 'from-blue-500 to-indigo-500',
            ],
            [
                'label' => 'Stok Minimum',
                'value' => number_format($statistics['low_stock'], 0, ',', '.'),
                'note' => 'Perlu diperiksa',
                'accent' => 'from-orange-400 to-amber-500',
            ],
            [
                'label' => 'Menunggu Persetujuan',
                'value' => number_format(
                    $statistics['pending_requests'],
                    0,
                    ',',
                    '.',
                ),
                'note' => 'Permintaan barang',
                'accent' => 'from-amber-400 to-yellow-500',
            ],
            [
                'label' => 'Permintaan Bulan Ini',
                'value' => number_format(
                    $statistics['requests_this_month'],
                    0,
                    ',',
                    '.',
                ),
                'note' => $displayNow->translatedFormat('F Y'),
                'accent' => 'from-violet-400 to-purple-500',
            ],
            [
                'label' => 'Motor Tersedia',
                'value' => number_format(
                    $statistics['available_vehicles'],
                    0,
                    ',',
                    '.',
                ),
                'note' => 'Siap dipinjam',
                'accent' => 'from-emerald-400 to-teal-500',
            ],
            [
                'label' => 'Motor Dipinjam',
                'value' => number_format(
                    $statistics['borrowed_vehicles'],
                    0,
                    ',',
                    '.',
                ),
                'note' => 'Sedang digunakan',
                'accent' => 'from-indigo-400 to-blue-600',
            ],
            [
                'label' => 'Dalam Pemeliharaan',
                'value' => number_format(
                    $statistics['maintenance_vehicles']
                        + $statistics['maintenance_assets'],
                    0,
                    ',',
                    '.',
                ),
                'note' => 'Kendaraan & perangkat',
                'accent' => 'from-rose-400 to-red-500',
            ],
            [
                'label' => 'Pegawai Aktif',
                'value' => number_format(
                    $statistics['active_employees'],
                    0,
                    ',',
                    '.',
                ),
                'note' => 'Akun pegawai',
                'accent' => 'from-cyan-400 to-sky-500',
            ],
        ];
    } else {
        $dashboardStatistics = [
            [
                'label' => 'Total Permintaan',
                'value' => number_format($statistics['requests'], 0, ',', '.'),
                'note' => 'Sepanjang waktu',
                'accent' => 'from-sky-400 to-blue-500',
            ],
            [
                'label' => 'Menunggu',
                'value' => number_format(
                    $statistics['pending_requests'],
                    0,
                    ',',
                    '.',
                ),
                'note' => 'Sedang diproses',
                'accent' => 'from-amber-400 to-orange-500',
            ],
            [
                'label' => 'Disetujui',
                'value' => number_format(
                    $statistics['approved_requests'],
                    0,
                    ',',
                    '.',
                ),
                'note' => 'Dapat ditindaklanjuti',
                'accent' => 'from-emerald-400 to-teal-500',
            ],
            [
                'label' => 'Peminjaman Motor',
                'value' => number_format(
                    $statistics['vehicle_loans'],
                    0,
                    ',',
                    '.',
                ),
                'note' => 'Sepanjang waktu',
                'accent' => 'from-violet-400 to-indigo-500',
            ],
            [
                'label' => 'Pinjaman Aktif',
                'value' => $statistics['active_loan'] ? 'Ada' : 'Tidak ada',
                'note' => 'Status saat ini',
                'accent' => 'from-blue-400 to-cyan-500',
            ],
        ];
    }
@endphp

<x-layouts.app
    title="Dashboard"
    header="{{ $isAdmin ? 'Dashboard Administrator' : 'Dashboard Saya' }}"
>
    <section class="relative isolate overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-[0_24px_65px_rgba(15,23,42,.20)] sm:p-8 xl:p-10">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_10%_15%,rgba(14,165,233,.25),transparent_30%),radial-gradient(circle_at_90%_90%,rgba(37,99,235,.20),transparent_28%)]"
            aria-hidden="true"
        ></div>

        <div
            class="pointer-events-none absolute inset-0 opacity-[.05] [background-image:linear-gradient(rgba(255,255,255,.6)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.6)_1px,transparent_1px)] [background-size:38px_38px]"
            aria-hidden="true"
        ></div>

        <div class="relative flex flex-col justify-between gap-7 xl:flex-row xl:items-end">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.13em] text-emerald-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    Ringkasan terbaru
                </span>

                <p class="mt-6 text-xs font-extrabold uppercase tracking-[.18em] text-sky-300">
                    {{ $isAdmin
                        ? 'Operasional SIMANTAP'
                        : ($user->work_unit ?: 'Unit kerja belum diisi') }}
                </p>

                <h1 class="mt-3 max-w-3xl text-3xl font-black leading-tight tracking-[-.04em] sm:text-4xl xl:text-5xl">
                    @if ($isAdmin)
                        Semua aset dalam satu kendali.
                    @else
                        Halo, {{ $user->name }}!
                    @endif
                </h1>

                <p class="mt-4 max-w-2xl text-sm font-medium leading-7 text-slate-300 sm:text-base">
                    @if ($isAdmin)
                        Pantau persediaan dan BMN melalui data operasional yang tercatat di dalam sistem.
                    @else
                        layanan terpadu permintaan barang dan peminjaman BMN serta stok persediaan barang secara real-time
                    @endif
                </p>
            </div>

            @if (! $isAdmin)
                <div class="flex flex-wrap gap-3">
                    @if (\Illuminate\Support\Facades\Route::has(
                        'my.inventory-requests.create',
                    ))
                        <a
                            href="{{ route('my.inventory-requests.create') }}"
                            class="button-primary-inline"
                        >
                            Ajukan Barang
                        </a>
                    @endif

                    @if (\Illuminate\Support\Facades\Route::has(
                        'my.vehicle-loans.create',
                    ))
                        <a
                            href="{{ route('my.vehicle-loans.create') }}"
                            class="button-secondary-dark"
                        >
                            Pinjam Motor
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </section>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($dashboardStatistics as $statistic)
            <article class="stat-card">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $statistic['accent'] }}"></div>

                <p class="text-xs font-extrabold text-slate-500">
                    {{ $statistic['label'] }}
                </p>

                <p class="mt-3 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">
                    {{ $statistic['value'] }}
                </p>

                <p class="mt-2 text-[11px] font-medium text-slate-400">
                    {{ $statistic['note'] }}
                </p>
            </article>
        @endforeach
    </section>

    @if ($isAdmin)
        @php
            $inventoryMaximum = max(
                1,
                ...$inventoryChart['stock_in'],
                ...$inventoryChart['stock_out'],
                ...$inventoryChart['requests'],
            );
            $vehicleMaximum = max(
                1,
                ...$vehicleChart['loans'],
                ...$vehicleChart['maintenance'],
            );
        @endphp

        <section class="mt-6 grid gap-6 xl:grid-cols-2">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Aktivitas Persediaan
                        </h2>

                        <p class="panel-subtitle">
                            Barang masuk, keluar, dan permintaan selama 6 bulan
                        </p>
                    </div>

                    <span class="status-badge">
                        6 bulan
                    </span>
                </div>

                <div class="overflow-x-auto p-5 sm:p-6">
                    <div class="min-w-[520px]">
                        <div class="flex items-center gap-5 text-[10px] font-bold text-slate-500">
                            <span class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span>
                                Barang masuk
                            </span>

                            <span class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                                Barang keluar
                            </span>

                            <span class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                                Permintaan
                            </span>
                        </div>

                        <div class="mt-6 grid grid-cols-6 gap-3">
                            @foreach ($inventoryChart['labels'] as $index => $label)
                                <div class="flex min-w-0 flex-col items-center">
                                    <div class="flex h-40 w-full items-end justify-center gap-1.5 border-b border-slate-200 px-1">
                                        @foreach ([
                                            [
                                                'value' => $inventoryChart['stock_in'][$index],
                                                'class' => 'bg-sky-500',
                                                'label' => 'Barang masuk',
                                            ],
                                            [
                                                'value' => $inventoryChart['stock_out'][$index],
                                                'class' => 'bg-indigo-500',
                                                'label' => 'Barang keluar',
                                            ],
                                            [
                                                'value' => $inventoryChart['requests'][$index],
                                                'class' => 'bg-amber-400',
                                                'label' => 'Permintaan',
                                            ],
                                        ] as $bar)
                                            @php
                                                $barHeight = $bar['value'] > 0
                                                    ? max(
                                                        6,
                                                        (int) round(
                                                            (
                                                                $bar['value']
                                                                / $inventoryMaximum
                                                            ) * 100,
                                                        ),
                                                    )
                                                    : 2;
                                            @endphp

                                            <span
                                                class="w-3 rounded-t-md {{ $bar['class'] }} transition-opacity hover:opacity-75"
                                                style="height: {{ $barHeight }}%"
                                                title="{{ $bar['label'] }}: {{ number_format($bar['value'], 2, ',', '.') }}"
                                            ></span>
                                        @endforeach
                                    </div>

                                    <span class="mt-3 text-[10px] font-extrabold text-slate-500">
                                        {{ $label }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Aktivitas Kendaraan
                        </h2>

                        <p class="panel-subtitle">
                            Peminjaman dan pemeliharaan selama 6 bulan
                        </p>
                    </div>

                    <span class="status-badge">
                        6 bulan
                    </span>
                </div>

                <div class="overflow-x-auto p-5 sm:p-6">
                    <div class="min-w-[520px]">
                        <div class="flex items-center gap-5 text-[10px] font-bold text-slate-500">
                            <span class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                Peminjaman
                            </span>

                            <span class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>
                                Pemeliharaan
                            </span>
                        </div>

                        <div class="mt-6 grid grid-cols-6 gap-3">
                            @foreach ($vehicleChart['labels'] as $index => $label)
                                <div class="flex min-w-0 flex-col items-center">
                                    <div class="flex h-40 w-full items-end justify-center gap-2 border-b border-slate-200 px-1">
                                        @foreach ([
                                            [
                                                'value' => $vehicleChart['loans'][$index],
                                                'class' => 'bg-emerald-500',
                                                'label' => 'Peminjaman',
                                            ],
                                            [
                                                'value' => $vehicleChart['maintenance'][$index],
                                                'class' => 'bg-rose-500',
                                                'label' => 'Pemeliharaan',
                                            ],
                                        ] as $bar)
                                            @php
                                                $barHeight = $bar['value'] > 0
                                                    ? max(
                                                        6,
                                                        (int) round(
                                                            (
                                                                $bar['value']
                                                                / $vehicleMaximum
                                                            ) * 100,
                                                        ),
                                                    )
                                                    : 2;
                                            @endphp

                                            <span
                                                class="w-4 rounded-t-md {{ $bar['class'] }} transition-opacity hover:opacity-75"
                                                style="height: {{ $barHeight }}%"
                                                title="{{ $bar['label'] }}: {{ number_format($bar['value'], 0, ',', '.') }}"
                                            ></span>
                                        @endforeach
                                    </div>

                                    <span class="mt-3 text-[10px] font-extrabold text-slate-500">
                                        {{ $label }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,.65fr)]">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Permintaan Terbaru
                        </h2>

                        <p class="panel-subtitle">
                            Pengajuan barang yang terakhir masuk
                        </p>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 md:hidden">
                    @forelse ($recentRequests as $request)
                        <div class="p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-slate-950">
                                        {{ $request->request_number }}
                                    </p>

                                    <p class="mt-1 truncate text-xs font-semibold text-slate-500">
                                        {{ $request->requester->name }}
                                    </p>
                                </div>

                                <span class="status-badge shrink-0">
                                    {{ $request->status->label() }}
                                </span>
                            </div>

                            <p class="mt-3 text-xs text-slate-400">
                                {{ $request->request_date->copy()->timezone($displayTimezone)->translatedFormat('d M Y') }}
                                · {{ $request->requester->work_unit ?: 'Tanpa unit' }}
                            </p>
                        </div>
                    @empty
                        <p class="empty-state">
                            Belum ada permintaan barang.
                        </p>
                    @endforelse
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nomor</th>
                                <th>Pegawai</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($recentRequests as $request)
                                <tr>
                                    <td class="font-bold text-slate-900">
                                        {{ $request->request_number }}
                                    </td>

                                    <td>
                                        <span class="block font-semibold text-slate-800">
                                            {{ $request->requester->name }}
                                        </span>

                                        <span class="text-xs text-slate-400">
                                            {{ $request->requester->work_unit ?: 'Tanpa unit' }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ $request->request_date->copy()->timezone($displayTimezone)->translatedFormat('d M Y') }}
                                    </td>

                                    <td>
                                        <span class="status-badge">
                                            {{ $request->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="empty-state">
                                        Belum ada permintaan barang.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Stok Hampir Habis
                        </h2>

                        <p class="panel-subtitle">
                            Stok tersedia mencapai batas minimum
                        </p>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 px-5">
                    @forelse ($lowStockItems as $item)
                        <div class="flex items-center gap-3 py-4">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-orange-50 text-xs font-black text-orange-600">
                                {{ mb_strtoupper(mb_substr($item->name, 0, 1)) }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-slate-900">
                                    {{ $item->name }}
                                </p>

                                <p class="mt-1 text-xs text-slate-400">
                                    Minimum
                                    {{ number_format($item->minimum_stock, 2, ',', '.') }}
                                    {{ $item->unit->symbol }}
                                </p>
                            </div>

                            <span class="rounded-lg bg-red-50 px-2.5 py-1 text-xs font-black text-red-700">
                                {{ number_format($item->available_stock, 2, ',', '.') }}
                            </span>
                        </div>
                    @empty
                        <p class="empty-state">
                            Semua stok berada pada batas aman.
                        </p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-3">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Peminjaman Terbaru
                        </h2>

                        <p class="panel-subtitle">
                            Pengajuan motor yang terakhir masuk
                        </p>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 px-5">
                    @forelse ($recentLoans as $loan)
                        <div class="py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-slate-900">
                                        {{ $loan->borrower->name }}
                                    </p>

                                    <p class="mt-1 truncate text-xs text-slate-400">
                                        {{ $loan->vehicle->license_plate }}
                                        · {{ $loan->loan_number }}
                                    </p>
                                </div>

                                <span class="status-badge shrink-0">
                                    {{ $loan->status->label() }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="empty-state">
                            Belum ada peminjaman motor.
                        </p>
                    @endforelse
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Belum Dikembalikan
                        </h2>

                        <p class="panel-subtitle">
                            Peminjaman melewati rencana pengembalian
                        </p>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 px-5">
                    @forelse ($overdueLoans as $loan)
                        <div class="py-4">
                            <p class="truncate text-sm font-bold text-slate-900">
                                {{ $loan->vehicle->license_plate }}
                                · {{ $loan->borrower->name }}
                            </p>

                            <p class="mt-1 text-xs font-semibold text-red-600">
                                Seharusnya kembali
                                {{ $loan->planned_end_at->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                            </p>
                        </div>
                    @empty
                        <p class="empty-state">
                            Tidak ada kendaraan terlambat.
                        </p>
                    @endforelse
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Pemeliharaan Aktif
                        </h2>

                        <p class="panel-subtitle">
                            Pekerjaan yang belum selesai
                        </p>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 px-5">
                    @forelse ($openMaintenance as $record)
                        <div class="py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-slate-900">
                                        @if ($record->vehicle !== null)
                                            {{ $record->vehicle->license_plate }}
                                            · {{ $record->vehicle->brand }}
                                        @elseif ($record->operationalAsset !== null)
                                            {{ $record->operationalAsset->asset_code }}
                                            · {{ $record->operationalAsset->brand }}
                                        @else
                                            {{ $record->subjectSnapshot() ?: 'Subjek pemeliharaan' }}
                                        @endif
                                    </p>

                                    <p class="mt-1 truncate text-xs text-slate-400">
                                        {{ $record->maintenance_number }}
                                    </p>
                                </div>

                                <span class="status-badge shrink-0">
                                    {{ $record->status->label() }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="empty-state">
                            Tidak ada pemeliharaan aktif.
                        </p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="mt-6">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Barang Paling Sering Diminta
                        </h2>

                        <p class="panel-subtitle">
                            Berdasarkan total jumlah pada pengajuan non-draft
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-5">
                    @forelse ($mostRequestedItems as $requestedItem)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                            <p class="truncate text-sm font-black text-slate-900">
                                {{ $requestedItem->item->name }}
                            </p>

                            <p class="mt-3 text-lg font-black text-sky-700">
                                {{ number_format(
                                    $requestedItem->total_requested,
                                    2,
                                    ',',
                                    '.',
                                ) }}
                                <span class="text-xs font-bold text-slate-400">
                                    {{ $requestedItem->item->unit->symbol }}
                                </span>
                            </p>
                        </div>
                    @empty
                        <p class="empty-state sm:col-span-2 lg:col-span-5">
                            Belum ada data permintaan untuk dirangkum.
                        </p>
                    @endforelse
                </div>
            </article>
        </section>
    @else
        <section class="mt-6 grid gap-6 xl:grid-cols-2">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Barang Tersedia
                        </h2>

                        <p class="panel-subtitle">
                            Persediaan yang dapat diajukan saat ini
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 p-5 sm:grid-cols-2">
                    @forelse ($availableItems as $item)
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-sky-200 hover:shadow-md">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-bold text-slate-900">
                                        {{ $item->name }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-400">
                                        {{ $item->item_code }}
                                    </p>
                                </div>

                                <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500"></span>
                            </div>

                            <p class="mt-4 text-sm font-black text-emerald-700">
                                {{ number_format($item->available_stock, 2, ',', '.') }}
                                {{ $item->unit->symbol }} tersedia
                            </p>
                        </div>
                    @empty
                        <p class="empty-state sm:col-span-2">
                            Belum ada barang yang tersedia.
                        </p>
                    @endforelse
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Motor Tersedia
                        </h2>

                        <p class="panel-subtitle">
                            Kendaraan yang siap diajukan
                        </p>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 px-5">
                    @forelse ($availableVehicles as $vehicle)
                        <div class="flex items-center justify-between gap-4 py-4">
                            <div class="min-w-0">
                                <p class="truncate font-bold text-slate-900">
                                    {{ $vehicle->brand }} {{ $vehicle->model }}
                                </p>

                                <p class="mt-1 truncate text-xs text-slate-400">
                                    {{ $vehicle->license_plate }}
                                    · {{ $vehicle->vehicle_code }}
                                </p>
                            </div>

                            <span class="status-badge shrink-0">
                                Tersedia
                            </span>
                        </div>
                    @empty
                        <p class="empty-state">
                            Belum ada motor yang tersedia.
                        </p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="mt-6">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Aktivitas Terakhir
                        </h2>

                        <p class="panel-subtitle">
                            Permintaan barang dan peminjaman motor Anda
                        </p>
                    </div>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse ($recentActivities as $activity)
                        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-[10px] font-black uppercase tracking-[.13em] text-sky-600">
                                    {{ $activity['type'] }}
                                </p>

                                <p class="mt-1 truncate text-sm font-bold text-slate-900">
                                    {{ $activity['title'] }}
                                </p>

                                <p class="mt-1 text-xs text-slate-400">
                                    {{ $activity['reference'] }}
                                    ·
                                    {{ $activity['occurred_at']->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                                </p>
                            </div>

                            <span class="status-badge w-fit shrink-0">
                                {{ $activity['status'] }}
                            </span>
                        </div>
                    @empty
                        <p class="empty-state">
                            Belum ada aktivitas permintaan atau peminjaman.
                        </p>
                    @endforelse
                </div>
            </article>
        </section>
    @endif
</x-layouts.app>

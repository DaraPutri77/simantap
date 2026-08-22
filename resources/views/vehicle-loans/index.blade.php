@php
    $hasFilters = $filters['search'] !== ''
        || $filters['status'] !== ''
        || $filters['from'] !== ''
        || $filters['until'] !== '';
@endphp

<x-layouts.app
    :title="$canViewAll ? 'Peminjaman Kendaraan' : 'Peminjaman Saya'"
    :header="$canViewAll ? 'Peminjaman Kendaraan' : 'Peminjaman Saya'"
    eyebrow="Kendaraan"
>
    <section class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">
                {{ $canViewAll ? 'Kendali Peminjaman' : 'Layanan Pegawai' }}
            </p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                {{ $canViewAll ? 'Peminjaman Kendaraan' : 'Peminjaman Saya' }}
            </h1>
            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-600">
                @if ($canViewAll)
                    Tinjau pengajuan, pastikan jadwal tidak berbenturan, dan
                    reservasi kendaraan operasional secara tertelusur.
                @else
                    Ajukan kendaraan dinas, pantau persetujuan, dan simpan
                    formulir peminjaman dalam satu halaman.
                @endif
            </p>
        </div>

        @if ($canViewAll && $canApprove)
            <a
                href="{{ route('vehicle-loans.approval-queue') }}"
                class="button-primary-inline"
            >
                Buka Antrean Approval
            </a>
        @elseif (! $canViewAll)
            <a
                href="{{ route('my.vehicle-loans.create') }}"
                class="button-primary-inline"
            >
                Buat Pengajuan Baru
            </a>
        @endif
    </section>

    <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Total', 'value' => $summary['total'], 'tone' => 'bg-sky-100 text-sky-900 ring-sky-300'],
            ['label' => 'Menunggu', 'value' => $summary['waiting'], 'tone' => 'bg-amber-100 text-amber-950 ring-amber-300'],
            ['label' => 'Disetujui', 'value' => $summary['approved'], 'tone' => 'bg-emerald-100 text-emerald-900 ring-emerald-300'],
            ['label' => 'Sedang Dipinjam', 'value' => $summary['active'], 'tone' => 'bg-cyan-100 text-cyan-950 ring-cyan-300'],
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
                <h2 class="panel-title">Daftar Peminjaman</h2>
                <p class="panel-subtitle">
                    {{ $vehicleLoans->total() }} peminjaman ditemukan
                </p>
            </div>
        </div>

        @if (! $canViewAll)
            <div class="mx-4 mt-4 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm font-semibold leading-6 text-sky-950">
                <strong>Bagian di bawah hanya untuk mencari dan menyaring riwayat peminjaman.</strong>
                Untuk membuat permohonan kendaraan baru, gunakan tombol
                <span class="font-black">Buat Pengajuan Baru</span>.
            </div>
        @endif

        <form
            method="GET"
            action="{{ route($routePrefix.'.index') }}"
            class="inventory-filter-grid"
        >
            <div>
                <label for="q" class="form-label">{{ $canViewAll ? 'Cari Peminjaman' : 'Cari Riwayat Peminjaman' }}</label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $filters['search'] }}"
                    class="form-input"
                    placeholder="{{ $canViewAll ? 'Nomor, pegawai, kendaraan, tujuan' : 'Nomor, kendaraan, atau tujuan' }}"
                >
            </div>
            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua status</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="from" class="form-label">Mulai Dari</label>
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
                <a href="{{ route($routePrefix.'.index') }}" class="secondary-button">
                    Reset
                </a>
                <button type="submit" class="button-primary-inline">
                    Terapkan Filter
                </button>
            </div>
        </form>

        @if ($vehicleLoans->isEmpty())
            <div class="empty-state">
                @if ($hasFilters)
                    <p class="font-extrabold text-slate-700">
                        Tidak ada peminjaman yang cocok dengan filter ini.
                    </p>
                    <p class="mt-1">Reset filter untuk melihat seluruh riwayat.</p>
                    <a
                        href="{{ route($routePrefix.'.index') }}"
                        class="secondary-button mt-4 inline-flex sm:w-auto"
                    >
                        Reset Filter
                    </a>
                @elseif ($canViewAll)
                    <p class="font-extrabold text-slate-700">
                        Belum ada pengajuan peminjaman kendaraan.
                    </p>
                    <p class="mt-1">Pengajuan pegawai yang sudah benar-benar dikirim akan muncul di sini.</p>
                @else
                    <p class="font-extrabold text-slate-700">
                        Anda belum memiliki riwayat peminjaman kendaraan.
                    </p>
                    <p class="mt-1">Pencarian kendaraan di halaman ini tidak membuat pengajuan baru.</p>
                    <a
                        href="{{ route('my.vehicle-loans.create') }}"
                        class="button-primary-inline mt-4"
                    >
                        Buat Pengajuan Baru
                    </a>
                @endif
            </div>
        @else
            <div class="grid gap-3 p-4 md:hidden">
                @foreach ($vehicleLoans as $vehicleLoan)
                    <a
                        href="{{ route($routePrefix.'.show', $vehicleLoan) }}"
                        class="rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-sky-300 hover:bg-sky-50"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-black text-slate-950">
                                    {{ $vehicleLoan->loan_number }}
                                </p>
                                <p class="mt-1 text-xs font-semibold text-slate-600">
                                    {{ $vehicleLoan->license_plate_snapshot }} ·
                                    {{ $vehicleLoan->vehicle_name_snapshot }}
                                </p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-black ring-1 ring-inset {{ $vehicleLoan->status->badgeClasses() }}">
                                {{ $vehicleLoan->status->label() }}
                            </span>
                        </div>
                        @if ($canViewAll)
                            <p class="mt-3 text-xs font-bold text-slate-700">
                                {{ $vehicleLoan->borrower_name_snapshot }} ·
                                {{ $vehicleLoan->work_unit_snapshot ?: '-' }}
                            </p>
                        @endif
                        <p class="mt-3 text-xs font-semibold leading-5 text-slate-600">
                            {{ $vehicleLoan->planned_start_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }}
                            –
                            {{ $vehicleLoan->planned_end_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                        </p>
                    </a>
                @endforeach
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nomor</th>
                            @if ($canViewAll)
                                <th>Peminjam</th>
                            @endif
                            <th>Kendaraan</th>
                            <th>Jadwal</th>
                            <th>Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vehicleLoans as $vehicleLoan)
                            <tr>
                                <td>
                                    <p class="font-black text-slate-950">
                                        {{ $vehicleLoan->loan_number }}
                                    </p>
                                    <p class="mt-1 max-w-xs truncate text-xs text-slate-500">
                                        {{ $vehicleLoan->destination }}
                                    </p>
                                </td>
                                @if ($canViewAll)
                                    <td>
                                        <p class="font-bold text-slate-900">
                                            {{ $vehicleLoan->borrower_name_snapshot }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $vehicleLoan->employee_number_snapshot ?: '-' }}
                                        </p>
                                    </td>
                                @endif
                                <td>
                                    <p class="font-bold text-slate-900">
                                        {{ $vehicleLoan->license_plate_snapshot }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $vehicleLoan->vehicle_name_snapshot }}
                                    </p>
                                </td>
                                <td class="whitespace-nowrap text-xs font-semibold leading-5 text-slate-600">
                                    {{ $vehicleLoan->planned_start_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }}<br>
                                    s.d. {{ $vehicleLoan->planned_end_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                                </td>
                                <td>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-black ring-1 ring-inset {{ $vehicleLoan->status->badgeClasses() }}">
                                        {{ $vehicleLoan->status->label() }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <a
                                        href="{{ route($routePrefix.'.show', $vehicleLoan) }}"
                                        class="inline-flex rounded-lg px-3 py-2 text-xs font-black text-sky-700 transition hover:bg-sky-50 hover:text-sky-900"
                                    >
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 p-4">
                {{ $vehicleLoans->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>

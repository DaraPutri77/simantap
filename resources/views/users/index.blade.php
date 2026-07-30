<x-layouts.app
    title="Manajemen Pengguna"
    header="Manajemen Pengguna"
    eyebrow="Administrator"
>
    <section class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">
                Akun Pegawai
            </p>

            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Manajemen Pengguna
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-medium leading-6 text-slate-500">
                Kelola identitas, aktivasi, status akses, dan keamanan akun
                Pegawai SIMANTAP.
            </p>
        </div>

        <div class="grid gap-3 sm:flex">
            <a
                href="{{ route('users.import') }}"
                class="secondary-button sm:w-auto"
            >
                Impor Data
            </a>

            <a
                href="{{ route('users.create') }}"
                class="button-primary-inline"
            >
                Tambah Pegawai
            </a>
        </div>
    </section>

    <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            [
                'label' => 'Total Pegawai',
                'value' => $summary['total'],
                'tone' => 'text-sky-700 bg-sky-50 ring-sky-100',
            ],
            [
                'label' => 'Akun Aktif',
                'value' => $summary['active'],
                'tone' => 'text-emerald-700 bg-emerald-50 ring-emerald-100',
            ],
            [
                'label' => 'Menunggu Aktivasi',
                'value' => $summary['pending'],
                'tone' => 'text-amber-700 bg-amber-50 ring-amber-100',
            ],
            [
                'label' => 'Dinonaktifkan',
                'value' => $summary['suspended'],
                'tone' => 'text-red-700 bg-red-50 ring-red-100',
            ],
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
                <h2 class="panel-title">
                    Daftar Pegawai
                </h2>

                <p class="panel-subtitle">
                    {{ $employees->total() }} pegawai ditemukan
                </p>
            </div>
        </div>

        <form
            method="GET"
            action="{{ route('users.index') }}"
            class="grid gap-3 border-b border-slate-100 p-5 md:grid-cols-[minmax(240px,1fr)_220px_220px_auto] md:items-end"
        >
            <div>
                <label
                    for="q"
                    class="form-label"
                >
                    Cari Pegawai
                </label>

                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $search }}"
                    class="form-input"
                    placeholder="Nama, NIP, atau email"
                >
            </div>

            <div>
                <label
                    for="status"
                    class="form-label"
                >
                    Status Akun
                </label>

                <select
                    id="status"
                    name="status"
                    class="form-input"
                >
                    <option value="">
                        Semua status
                    </option>

                    @foreach ($statusOptions as $statusOption)
                        <option
                            value="{{ $statusOption->value }}"
                            @selected(
                                $selectedStatus === $statusOption->value
                            )
                        >
                            {{ $statusOption->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label
                    for="work_unit"
                    class="form-label"
                >
                    Unit Kerja
                </label>

                <select
                    id="work_unit"
                    name="work_unit"
                    class="form-input"
                >
                    <option value="">
                        Semua unit
                    </option>

                    @foreach ($workUnits as $unit)
                        <option
                            value="{{ $unit }}"
                            @selected($selectedWorkUnit === $unit)
                        >
                            {{ $unit }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2 md:flex">
                <a
                    href="{{ route('users.index') }}"
                    class="secondary-button md:w-auto"
                >
                    Reset
                </a>

                <button
                    type="submit"
                    class="button-primary-inline"
                >
                    Terapkan
                </button>
            </div>
        </form>

        @if ($employees->isEmpty())
            <div class="empty-state">
                <p class="font-extrabold text-slate-600">
                    Data pegawai tidak ditemukan.
                </p>

                <p class="mt-1">
                    Ubah filter atau tambahkan akun Pegawai baru.
                </p>
            </div>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Pegawai</th>
                            <th>Unit & Jabatan</th>
                            <th>Status</th>
                            <th>Aktivitas</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($employees as $employee)
                            @php
                                $statusTone = match (
                                    $employee->status
                                ) {
                                    \App\Enums\AccountStatus::Active
                                        => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
                                    \App\Enums\AccountStatus::PendingActivation
                                        => 'bg-amber-50 text-amber-700 ring-amber-100',
                                    \App\Enums\AccountStatus::Suspended
                                        => 'bg-red-50 text-red-700 ring-red-100',
                                };
                            @endphp

                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-950 text-xs font-black text-white">
                                            {{ mb_strtoupper(
                                                mb_substr(
                                                    $employee->name,
                                                    0,
                                                    1,
                                                ),
                                            ) }}
                                        </span>

                                        <span class="min-w-0">
                                            <a
                                                href="{{ route(
                                                    'users.show',
                                                    $employee,
                                                ) }}"
                                                class="block truncate font-extrabold text-slate-950 transition hover:text-sky-700"
                                            >
                                                {{ $employee->name }}
                                            </a>

                                            <span class="mt-1 block truncate text-xs text-slate-400">
                                                {{ $employee->employee_number }}
                                                · {{ $employee->email }}
                                            </span>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <p class="font-bold text-slate-800">
                                        {{ $employee->work_unit }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-400">
                                        {{ $employee->position }}
                                    </p>
                                </td>

                                <td>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-extrabold ring-1 ring-inset {{ $statusTone }}">
                                        {{ $employee->status->label() }}
                                    </span>
                                </td>

                                <td>
                                    <p class="font-bold text-slate-800">
                                        {{ $employee->inventory_requests_count }}
                                        permintaan
                                    </p>

                                    <p class="mt-1 text-xs text-slate-400">
                                        {{ $employee->vehicle_loans_count }}
                                        peminjaman
                                    </p>
                                </td>

                                <td class="text-right">
                                    <a
                                        href="{{ route(
                                            'users.show',
                                            $employee,
                                        ) }}"
                                        class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 px-3 py-2 text-xs font-extrabold text-slate-600 transition hover:border-sky-200 hover:text-sky-700"
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
                @foreach ($employees as $employee)
                    @php
                        $statusTone = match ($employee->status) {
                            \App\Enums\AccountStatus::Active
                                => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
                            \App\Enums\AccountStatus::PendingActivation
                                => 'bg-amber-50 text-amber-700 ring-amber-100',
                            \App\Enums\AccountStatus::Suspended
                                => 'bg-red-50 text-red-700 ring-red-100',
                        };
                    @endphp

                    <article class="p-5">
                        <div class="flex items-start gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-slate-950 text-sm font-black text-white">
                                {{ mb_strtoupper(
                                    mb_substr($employee->name, 0, 1),
                                ) }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate font-black text-slate-950">
                                    {{ $employee->name }}
                                </p>

                                <p class="mt-1 truncate text-xs text-slate-400">
                                    {{ $employee->employee_number }}
                                </p>
                            </div>

                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-extrabold ring-1 ring-inset {{ $statusTone }}">
                                {{ $employee->status->label() }}
                            </span>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-3 rounded-2xl bg-slate-50 p-4 text-xs">
                            <div>
                                <dt class="font-bold text-slate-400">
                                    Unit kerja
                                </dt>
                                <dd class="mt-1 font-extrabold text-slate-800">
                                    {{ $employee->work_unit }}
                                </dd>
                            </div>

                            <div>
                                <dt class="font-bold text-slate-400">
                                    Jabatan
                                </dt>
                                <dd class="mt-1 font-extrabold text-slate-800">
                                    {{ $employee->position }}
                                </dd>
                            </div>
                        </dl>

                        <a
                            href="{{ route('users.show', $employee) }}"
                            class="secondary-button mt-4"
                        >
                            Lihat Detail
                        </a>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-100 px-5 py-4">
                {{ $employees->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>

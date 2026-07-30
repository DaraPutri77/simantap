<x-layouts.app
    title="Detail Pegawai"
    header="Detail Pegawai"
    eyebrow="Manajemen Pengguna"
>
    @php
        $statusTone = match ($employee->status) {
            \App\Enums\AccountStatus::Active
                => 'bg-emerald-400/15 text-emerald-200 ring-emerald-300/20',
            \App\Enums\AccountStatus::PendingActivation
                => 'bg-amber-400/15 text-amber-200 ring-amber-300/20',
            \App\Enums\AccountStatus::Suspended
                => 'bg-red-400/15 text-red-200 ring-red-300/20',
        };
        $createdAt = $employee->created_at?->copy()->timezone(
            $displayTimezone,
        );
        $activatedAt = $employee->activated_at?->copy()->timezone(
            $displayTimezone,
        );
        $lastLoginAt = $employee->last_login_at?->copy()->timezone(
            $displayTimezone,
        );
    @endphp

    @if ($errors->any())
        <div class="alert-danger mb-6" role="alert">
            <div>
                <p class="font-extrabold">
                    Tindakan belum dapat diproses.
                </p>

                <ul class="mt-1 list-disc space-y-1 pl-5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-[0_24px_65px_rgba(15,23,42,.18)] sm:p-8">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_16%,rgba(14,165,233,.24),transparent_30%),radial-gradient(circle_at_88%_84%,rgba(37,99,235,.18),transparent_28%)]"
            aria-hidden="true"
        ></div>

        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center">
            <span class="grid h-18 w-18 shrink-0 place-items-center rounded-3xl bg-gradient-to-br from-sky-400 to-blue-700 text-2xl font-black shadow-xl shadow-blue-950/30">
                {{ mb_strtoupper(
                    mb_substr($employee->name, 0, 1),
                ) }}
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-extrabold uppercase tracking-[.16em] text-sky-300">
                        {{ $employee->employee_number }}
                    </p>

                    <span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold ring-1 ring-inset {{ $statusTone }}">
                        {{ $employee->status->label() }}
                    </span>
                </div>

                <h1 class="mt-2 break-words text-3xl font-black tracking-tight sm:text-4xl">
                    {{ $employee->name }}
                </h1>

                <p class="mt-2 text-sm font-medium text-slate-300">
                    {{ $employee->position }}
                    · {{ $employee->work_unit }}
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:flex">
                <a
                    href="{{ route('users.index') }}"
                    class="button-secondary-dark"
                >
                    Kembali
                </a>

                <a
                    href="{{ route('users.edit', $employee) }}"
                    class="button-primary-inline"
                >
                    Edit Data
                </a>
            </div>
        </div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-6">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Informasi Pegawai
                        </h2>

                        <p class="panel-subtitle">
                            Identitas dan penempatan kerja
                        </p>
                    </div>
                </div>

                <dl class="grid gap-x-8 gap-y-6 p-5 sm:grid-cols-2 sm:p-6">
                    @foreach ([
                        [
                            'NIP / Nomor Pegawai',
                            $employee->employee_number,
                        ],
                        ['Nama Lengkap', $employee->name],
                        ['Email', $employee->email],
                        [
                            'Nomor Telepon',
                            $employee->phone ?: 'Belum diisi',
                        ],
                        ['Unit Kerja', $employee->work_unit],
                        ['Jabatan', $employee->position],
                    ] as [$label, $value])
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-[.13em] text-slate-400">
                                {{ $label }}
                            </dt>

                            <dd class="mt-2 break-words text-sm font-bold text-slate-900">
                                {{ $value }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Ringkasan Aktivitas
                        </h2>

                        <p class="panel-subtitle">
                            Aktivitas bisnis yang terhubung dengan pegawai
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 p-5 sm:p-6">
                    <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4">
                        <p class="text-xs font-bold text-sky-700">
                            Permintaan Barang
                        </p>

                        <p class="mt-2 text-3xl font-black text-sky-950">
                            {{ $employee->inventory_requests_count }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                        <p class="text-xs font-bold text-emerald-700">
                            Peminjaman Motor
                        </p>

                        <p class="mt-2 text-3xl font-black text-emerald-950">
                            {{ $employee->vehicle_loans_count }}
                        </p>
                    </div>
                </div>
            </article>
        </div>

        <aside class="space-y-6">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Status & Keamanan
                        </h2>

                        <p class="panel-subtitle">
                            Akses akun Pegawai
                        </p>
                    </div>
                </div>

                <dl class="space-y-5 p-5">
                    @foreach ([
                        [
                            'Akun Dibuat',
                            $createdAt?->translatedFormat(
                                'd F Y, H:i',
                            ).' WIB',
                        ],
                        [
                            'Aktivasi',
                            $activatedAt
                                ? $activatedAt->translatedFormat(
                                    'd F Y, H:i',
                                ).' WIB'
                                : 'Belum diaktivasi',
                        ],
                        [
                            'Login Berhasil Terakhir',
                            $lastLoginAt
                                ? $lastLoginAt->translatedFormat(
                                    'd F Y, H:i',
                                ).' WIB'
                                : 'Belum tercatat',
                        ],
                        [
                            'Dibuat Oleh',
                            $employee->creator?->name
                                ?: 'Data instalasi',
                        ],
                    ] as [$label, $value])
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-[.13em] text-slate-400">
                                {{ $label }}
                            </dt>

                            <dd class="mt-2 text-sm font-bold text-slate-900">
                                {{ $value }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Tindakan Akun
                        </h2>

                        <p class="panel-subtitle">
                            Tindakan dicatat pada Audit Log
                        </p>
                    </div>
                </div>

                <div class="space-y-3 p-5">
                    @if (
                        $employee->status
                            === \App\Enums\AccountStatus::PendingActivation
                    )
                        <form
                            method="POST"
                            action="{{ route(
                                'users.activation.resend',
                                $employee,
                            ) }}"
                            data-confirm-message="Kirim tautan aktivasi baru ke {{ $employee->email }}?"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="button-primary-inline w-full"
                            >
                                Kirim Ulang Aktivasi
                            </button>
                        </form>

                        <p class="rounded-2xl bg-amber-50 p-4 text-xs font-semibold leading-5 text-amber-800">
                            Tautan lama otomatis tidak berlaku setelah tautan
                            baru dibuat.
                        </p>
                    @elseif (
                        $employee->status
                            === \App\Enums\AccountStatus::Active
                    )
                        <form
                            method="POST"
                            action="{{ route(
                                'users.password-reset.send',
                                $employee,
                            ) }}"
                            data-confirm-message="Kirim tautan reset kata sandi ke {{ $employee->email }}?"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="secondary-button"
                            >
                                Kirim Reset Kata Sandi
                            </button>
                        </form>

                        <form
                            method="POST"
                            action="{{ route(
                                'users.suspend',
                                $employee,
                            ) }}"
                            data-confirm-message="Nonaktifkan akun {{ $employee->name }}? Sesi login yang masih aktif akan dihentikan."
                        >
                            @csrf
                            @method('PATCH')

                            <button
                                type="submit"
                                class="flex min-h-12 w-full items-center justify-center rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-extrabold text-red-700 transition hover:border-red-300 hover:bg-red-100 focus-visible:ring-red-100"
                            >
                                Nonaktifkan Akun
                            </button>
                        </form>
                    @else
                        <form
                            method="POST"
                            action="{{ route(
                                'users.reactivate',
                                $employee,
                            ) }}"
                            data-confirm-message="Aktifkan kembali akun {{ $employee->name }}?"
                        >
                            @csrf
                            @method('PATCH')

                            <button
                                type="submit"
                                class="button-primary-inline w-full"
                            >
                                Aktifkan Kembali
                            </button>
                        </form>
                    @endif
                </div>
            </article>
        </aside>
    </section>
</x-layouts.app>

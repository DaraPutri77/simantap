<x-layouts.app
    title="Profil"
    header="Profil Saya"
    eyebrow="Akun"
>
    @php
        $displayTimezone = 'Asia/Jakarta';
        $lastLoginAt = $user->last_login_at?->copy()->timezone(
            $displayTimezone,
        );
    @endphp

    <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-[0_24px_65px_rgba(15,23,42,.18)] sm:p-8">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_16%,rgba(14,165,233,.24),transparent_30%),radial-gradient(circle_at_88%_84%,rgba(37,99,235,.18),transparent_28%)]"
            aria-hidden="true"
        ></div>

        <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center">
            <span class="grid h-18 w-18 shrink-0 place-items-center rounded-3xl bg-gradient-to-br from-sky-400 to-blue-700 text-2xl font-black shadow-xl shadow-blue-950/30">
                {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
            </span>

            <div class="min-w-0">
                <p class="text-xs font-extrabold uppercase tracking-[.16em] text-sky-300">
                    Data diri
                </p>

                <h1 class="mt-2 truncate text-3xl font-black tracking-tight sm:text-4xl">
                    {{ $user->name }}
                </h1>

                <p class="mt-2 text-sm font-medium text-slate-300">
                    {{ $user->position ?: 'Jabatan belum diisi' }}
                    · {{ $user->work_unit ?: 'Unit kerja belum diisi' }}
                </p>
            </div>

            <a
                href="{{ route('profile.edit') }}"
                class="button-secondary-dark sm:ml-auto"
            >
                <svg
                    viewBox="0 0 24 24"
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M12 20h9"/>
                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>
                </svg>

                Edit Profil
            </a>
        </div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <article class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">
                        Informasi Pegawai
                    </h2>

                    <p class="panel-subtitle">
                        Data identitas yang tersimpan di SIMANTAP
                    </p>
                </div>
            </div>

            <dl class="grid gap-x-8 gap-y-6 p-5 sm:grid-cols-2 sm:p-6">
                @foreach ([
                    ['Nomor Pegawai / NIP', $user->employee_number],
                    ['Nama Lengkap', $user->name],
                    ['Email', $user->email],
                    ['Nomor Telepon', $user->phone],
                    ['Unit Kerja', $user->work_unit],
                    ['Jabatan', $user->position],
                ] as [$label, $value])
                    <div>
                        <dt class="text-[10px] font-black uppercase tracking-[.13em] text-slate-400">
                            {{ $label }}
                        </dt>

                        <dd class="mt-2 break-words text-sm font-bold text-slate-900">
                            {{ $value ?: 'Belum diisi' }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </article>

        <aside class="panel h-fit">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">
                        Keamanan Akun
                    </h2>

                    <p class="panel-subtitle">
                        Informasi akses akun Anda
                    </p>
                </div>
            </div>

            <div class="space-y-4 p-5">
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[.13em] text-emerald-700">
                        Status akun
                    </p>

                    <p class="mt-2 text-sm font-black text-emerald-900">
                        {{ $user->status->label() }}
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[.13em] text-slate-400">
                        Login berhasil terakhir
                    </p>

                    <p class="mt-2 text-sm font-bold text-slate-900">
                        @if ($lastLoginAt !== null)
                            {{ $lastLoginAt->translatedFormat(
                                'd F Y, H:i',
                            ).' '.$lastLoginAt->format('T') }}
                        @else
                            Belum tercatat
                        @endif
                    </p>

                    <p class="mt-1 text-[11px] font-medium leading-5 text-slate-400">
                        Waktu login sukses terbaru dalam zona Asia/Jakarta.
                    </p>
                </div>

                <a
                    href="{{ route('password.change') }}"
                    class="button-primary-inline w-full"
                >
                    Ubah Kata Sandi
                </a>
            </div>
        </aside>
    </section>
</x-layouts.app>

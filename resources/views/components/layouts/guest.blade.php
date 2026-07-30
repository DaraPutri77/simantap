<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        {{ $title ?? 'Masuk' }} · {{ config('app.name') }}
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body class="min-h-screen bg-slate-950 text-slate-900 antialiased">
    <main class="relative isolate min-h-screen overflow-hidden">
        <div
            class="absolute inset-0 bg-[radial-gradient(circle_at_15%_18%,rgba(14,165,233,.22),transparent_30%),radial-gradient(circle_at_88%_82%,rgba(249,115,22,.14),transparent_28%)]"
        ></div>

        <div
            class="absolute -left-20 top-24 h-64 w-64 rounded-full border border-white/10"
        ></div>

        <div
            class="absolute -bottom-32 right-20 h-96 w-96 rounded-full border border-white/10"
        ></div>

        <div
            class="relative mx-auto grid min-h-screen max-w-7xl items-center gap-10 px-5 py-8 lg:grid-cols-[1.05fr_.95fr] lg:px-10"
        >
            <section class="hidden px-6 text-white lg:block">
                <a
                    href="{{ url('/') }}"
                    class="inline-flex items-center gap-3"
                    aria-label="Beranda SIMANTAP"
                >
                    <span
                        class="grid h-12 w-12 place-items-center rounded-2xl bg-sky-500 shadow-lg shadow-sky-500/25"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="h-7 w-7"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 7.5 12 3l8 4.5-8 4.5-8-4.5Z"
                            />

                            <path
                                d="M4 12.5 12 17l8-4.5M4 17l8 4 8-4"
                            />
                        </svg>
                    </span>

                    <span>
                        <span
                            class="block text-xl font-extrabold tracking-[.08em]"
                        >
                            SIMANTAP
                        </span>

                        <span class="text-xs text-slate-400">
                            Sistem Manajemen Aset dan Persediaan
                        </span>
                    </span>
                </a>

                <div class="mt-16 max-w-xl">
                    <p
                        class="mb-5 inline-flex items-center gap-2 rounded-full border border-sky-400/25 bg-sky-400/10 px-4 py-2 text-sm font-semibold text-sky-300"
                    >
                        <span
                            class="h-2 w-2 rounded-full bg-sky-400"
                        ></span>

                        Platform internal terintegrasi
                    </p>

                    <h1
                        class="text-5xl font-black leading-[1.08] tracking-tight"
                    >
                        Kelola aset dengan

                        <span class="text-sky-400">
                            lebih tertib
                        </span>,

                        cepat, dan transparan.
                    </h1>

                    <p
                        class="mt-6 max-w-lg text-lg leading-8 text-slate-300"
                    >
                        Satu sistem untuk persediaan, permintaan
                        barang, kendaraan dinas, pemeliharaan,
                        dan jejak audit.
                    </p>
                </div>

                <div
                    class="mt-12 grid max-w-xl grid-cols-3 gap-3"
                >
                    @foreach ([
                        [
                            'Persediaan',
                            'Stok real-time',
                        ],
                        [
                            'Kendaraan',
                            'Jadwal terkontrol',
                        ],
                        [
                            'Audit',
                            'Riwayat tercatat',
                        ],
                    ] as [$label, $description])
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[.06] p-4 backdrop-blur-sm"
                        >
                            <p class="font-bold">
                                {{ $label }}
                            </p>

                            <p class="mt-1 text-xs text-slate-400">
                                {{ $description }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="mx-auto w-full max-w-lg">
                <div
                    class="mb-6 flex items-center justify-center gap-3 text-white lg:hidden"
                >
                    <span
                        class="grid h-11 w-11 place-items-center rounded-2xl bg-sky-500"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 7.5 12 3l8 4.5-8 4.5-8-4.5Z"
                            />

                            <path
                                d="M4 12.5 12 17l8-4.5M4 17l8 4 8-4"
                            />
                        </svg>
                    </span>

                    <span>
                        <span
                            class="block font-extrabold tracking-[.08em]"
                        >
                            SIMANTAP
                        </span>

                        <span
                            class="block text-[10px] text-slate-400"
                        >
                            Manajemen Aset & Persediaan
                        </span>
                    </span>
                </div>

                <div
                    class="rounded-[2rem] border border-white/20 bg-white p-6 shadow-2xl shadow-black/30 sm:p-9"
                >
                    @if (session('status'))
                        <div
                            class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                            role="status"
                        >
                            {{ session('status') }}
                        </div>
                    @endif

                    @if (session('warning'))
                        <div
                            class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                            role="alert"
                        >
                            {{ session('warning') }}
                        </div>
                    @endif

                    {{ $slot }}
                </div>

                <p class="mt-5 text-center text-xs text-slate-500">
                    © {{ now()->year }}
                    {{ config('simantap.institution.name') }}
                    · Akses terbatas untuk pengguna terdaftar
                </p>
            </section>
        </div>
    </main>
</body>
</html>
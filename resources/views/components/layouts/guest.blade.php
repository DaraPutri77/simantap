<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#020617">

    <title>{{ $title ?? 'Masuk' }} · {{ config('app.name') }}</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body class="min-h-screen bg-slate-950 text-slate-900 antialiased">
    <main class="relative isolate min-h-screen overflow-hidden">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_12%,rgba(14,165,233,.20),transparent_28%),radial-gradient(circle_at_88%_88%,rgba(249,115,22,.12),transparent_25%)]"
            aria-hidden="true"
        ></div>

        <div
            class="pointer-events-none absolute inset-0 opacity-[.045] [background-image:linear-gradient(rgba(255,255,255,.5)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.5)_1px,transparent_1px)] [background-size:42px_42px]"
            aria-hidden="true"
        ></div>

        <div
            class="pointer-events-none absolute -left-32 top-32 h-96 w-96 rounded-full border border-white/10"
            aria-hidden="true"
        ></div>

        <div
            class="pointer-events-none absolute -bottom-52 right-12 h-[34rem] w-[34rem] rounded-full border border-white/[.08]"
            aria-hidden="true"
        ></div>

        <div class="relative mx-auto grid min-h-screen max-w-[1440px] lg:grid-cols-[minmax(0,1.08fr)_minmax(440px,.92fr)]">
            <section class="relative hidden flex-col justify-between overflow-hidden px-10 py-9 text-white lg:flex xl:px-16 xl:py-12">
                <header>
                    <a
                        href="{{ url('/') }}"
                        class="inline-flex items-center gap-3.5 rounded-2xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-sky-400/30"
                        aria-label="Beranda SIMANTAP"
                    >
                        <img
                            src="{{ asset(config('simantap.institution.logo')) }}"
                            alt="{{ config('simantap.institution.name') }}"
                            class="h-auto w-[320px] max-w-full"
                        >
                    </a>
                </header>

                <div class="my-auto max-w-2xl py-10">
                    <div class="inline-flex items-center gap-2.5 rounded-full border border-sky-400/20 bg-sky-400/10 px-4 py-2 text-xs font-extrabold text-sky-300 backdrop-blur-sm">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                        </span>

                        Platform internal terintegrasi
                    </div>

                    <h1 class="mt-7 max-w-2xl text-[clamp(2.8rem,4.3vw,4.9rem)] font-black leading-[1.02] tracking-[-.045em]">
                        Kendali aset
                        <span class="relative inline-block text-sky-400">
                            lebih cerdas
                            <svg
                                viewBox="0 0 260 12"
                                class="absolute -bottom-2 left-0 w-full text-sky-500/60"
                                fill="none"
                                aria-hidden="true"
                            >
                                <path
                                    d="M3 8.5C64 2.5 155 2 257 7"
                                    stroke="currentColor"
                                    stroke-width="5"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </span>
                        dalam satu sistem.
                    </h1>

                    <p class="mt-7 max-w-xl text-base font-medium leading-8 text-slate-300 xl:text-lg">
                        Pengelolaan permintaan barang dan peminjaman BMN untuk
                        kerja yang lebih efisien dan transparan.
                    </p>

                    <div class="mt-9 grid max-w-2xl grid-cols-3 gap-3">
                        @foreach ([
                            [
                                'Persediaan',
                                'Stok terpantau',
                                'M4 6h16v12H4zM8 3h8v3H8zM8 10h3m2 0h3M8 14h3m2 0h3',
                            ],
                            [
                                'Kendaraan',
                                'Jadwal terkendali',
                                'M5 17h14M6 17l1-7h10l1 7M8 17v2m8-2v2M8 13h8M9 7h6',
                            ],
                            [
                                'Audit',
                                'Riwayat tercatat',
                                'M12 3 5 6v5c0 4.6 2.8 8 7 10 4.2-2 7-5.4 7-10V6l-7-3Zm-3 9 2 2 4-4',
                            ],
                        ] as [$label, $description, $path])
                            <article class="group rounded-3xl border border-white/10 bg-white/[.055] p-4 backdrop-blur-sm transition hover:-translate-y-1 hover:border-sky-400/25 hover:bg-white/[.08]">
                                <span class="grid h-9 w-9 place-items-center rounded-xl bg-white/[.08] text-sky-300 ring-1 ring-inset ring-white/10">
                                    <svg
                                        viewBox="0 0 24 24"
                                        class="h-4.5 w-4.5"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path d="{{ $path }}"/>
                                    </svg>
                                </span>

                                <p class="mt-4 text-sm font-black">
                                    {{ $label }}
                                </p>

                                <p class="mt-1 text-[11px] font-medium text-slate-400">
                                    {{ $description }}
                                </p>
                            </article>
                        @endforeach
                    </div>
                </div>

                <footer class="flex items-center justify-between gap-6 text-[11px] font-semibold text-slate-500">
                    <span class="inline-flex items-center gap-2">
                        <svg
                            viewBox="0 0 24 24"
                            class="h-4 w-4 text-emerald-400"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>
                            <path d="m9 12 2 2 4-4"/>
                        </svg>

                        Akses aman untuk pengguna terverifikasi
                    </span>

                    <span>©  {{ config('simantap.institution.short_name') }}</span>
                </footer>
            </section>

            <section class="relative flex min-h-screen items-center justify-center px-4 py-6 sm:px-8 lg:bg-white/[.035] lg:px-10 xl:px-16">
                <div class="w-full max-w-[560px]">
                    <div class="mb-6 flex items-center justify-center lg:hidden">
                        <a
                            href="{{ url('/') }}"
                            class="inline-flex max-w-full items-center justify-center rounded-2xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-sky-400/30"
                            aria-label="Beranda SIMANTAP - {{ config('simantap.institution.name') }}"
                        >
                            <img
                                src="{{ asset(config('simantap.institution.logo')) }}"
                                alt="{{ config('simantap.institution.name') }}"
                                class="h-auto w-[250px] max-w-[78vw] sm:w-[300px]"
                            >
                        </a>
                    </div>

                    <div class="relative overflow-hidden rounded-[2rem] border border-white/40 bg-white p-6 shadow-[0_32px_90px_rgba(2,6,23,.34)] sm:p-9 xl:p-10">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-sky-400 via-blue-600 to-orange-400"></div>

                        @if (session('status'))
                            <div class="alert-success mb-6" role="status">
                                <svg
                                    viewBox="0 0 24 24"
                                    class="mt-0.5 h-5 w-5 shrink-0"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    aria-hidden="true"
                                >
                                    <path d="m5 12 4 4L19 6"/>
                                </svg>

                                <span>{{ session('status') }}</span>
                            </div>
                        @endif

                        @if (session('warning'))
                            <div class="alert-warning mb-6" role="alert">
                                <svg
                                    viewBox="0 0 24 24"
                                    class="mt-0.5 h-5 w-5 shrink-0"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    aria-hidden="true"
                                >
                                    <path d="M12 9v4m0 4h.01M10.3 3.6 2.4 17.2A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.8L13.7 3.6a2 2 0 0 0-3.4 0Z"/>
                                </svg>

                                <span>{{ session('warning') }}</span>
                            </div>
                        @endif

                        {{ $slot }}
                    </div>

                    <p class="mt-5 text-center text-[11px] font-medium leading-5 text-slate-500">
                        ©  {{ config('simantap.institution.name') }}
                        <span class="mx-1.5 text-slate-700">•</span>
                        Akses terbatas untuk pengguna terdaftar
                    </p>
                </div>
            </section>
        </div>
    </main>
</body>
</html>

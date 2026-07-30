@props([
    'title' => 'Dashboard',
    'header' => 'Dashboard',
    'eyebrow' => 'Ruang kerja',
])

@php
    $currentUser = auth()->user();
    $navigation = \App\Support\Navigation::for($currentUser);
    $isAdministrator = $currentUser->hasRole(
        \App\Enums\RoleName::Administrator->value,
    );
    $roleLabel = $isAdministrator
        ? \App\Enums\RoleName::Administrator->label()
        : \App\Enums\RoleName::Employee->label();
    $displayNow = now()->timezone(
        config('simantap.display_timezone', 'Asia/Jakarta'),
    );
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#020617">

    <title>{{ $title }} · {{ config('app.name') }}</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body class="min-h-screen bg-[#f4f7fb] text-slate-900 antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-[280px_minmax(0,1fr)]">
        <button
            id="sidebar-backdrop"
            type="button"
            class="fixed inset-0 z-30 hidden bg-slate-950/65 backdrop-blur-sm lg:hidden"
            aria-label="Tutup menu"
            tabindex="-1"
        ></button>

        <aside
            id="sidebar"
            class="fixed inset-y-0 left-0 z-40 flex w-[280px] -translate-x-full flex-col overflow-hidden bg-slate-950 text-white shadow-2xl shadow-slate-950/30 transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
            aria-label="Navigasi SIMANTAP"
        >
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_10%_8%,rgba(14,165,233,.18),transparent_26%),radial-gradient(circle_at_90%_92%,rgba(37,99,235,.14),transparent_28%)]"
                aria-hidden="true"
            ></div>

            <div class="relative flex h-20 items-center gap-3 border-b border-white/[.08] px-5">
                <span class="brand-mark h-11 w-11">
                    <svg
                        viewBox="0 0 24 24"
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="m4 7.5 8-4.5 8 4.5-8 4.5-8-4.5Z"/>
                        <path d="m4 12.5 8 4.5 8-4.5M4 17l8 4 8-4"/>
                    </svg>
                </span>

                <span class="min-w-0">
                    <span class="block text-lg font-black tracking-[.1em]">
                        SIMANTAP
                    </span>

                    <span class="mt-0.5 block truncate text-[9px] font-bold uppercase tracking-[.13em] text-slate-500">
                        Aset & Persediaan
                    </span>
                </span>

                <button
                    id="sidebar-close"
                    type="button"
                    class="ml-auto grid h-9 w-9 place-items-center rounded-xl text-slate-400 transition hover:bg-white/[.07] hover:text-white lg:hidden"
                    aria-label="Tutup menu"
                >
                    <svg
                        viewBox="0 0 24 24"
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        aria-hidden="true"
                    >
                        <path d="m6 6 12 12M18 6 6 18"/>
                    </svg>
                </button>
            </div>

            <nav
                class="relative flex-1 overflow-y-auto px-4 py-6"
                aria-label="Menu utama"
            >
                <p class="sidebar-section-label">
                    Menu utama
                </p>

                <div class="mt-3 space-y-1">
                    @foreach ($navigation as $menu)
                        <a
                            href="{{ route($menu['route']) }}"
                            class="sidebar-link {{ $menu['is_active']
                                ? 'sidebar-link-active'
                                : '' }}"
                            @if ($menu['is_active'])
                                aria-current="page"
                            @endif
                        >
                            <x-navigation-icon :name="$menu['icon']" />

                            <span class="truncate">
                                {{ $menu['label'] }}
                            </span>

                            @if ($menu['is_active'])
                                <span class="ml-auto h-1.5 w-1.5 rounded-full bg-white shadow-[0_0_0_4px_rgba(255,255,255,.12)]"></span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </nav>

            <div class="relative border-t border-white/[.08] p-4">
                <p class="sidebar-section-label mb-3">
                    Akses akun Anda
                </p>

                <div class="rounded-2xl border border-white/[.08] bg-white/[.055] p-3.5">
                    <div class="flex items-center gap-3">
                        <span class="relative grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-sky-400 to-blue-700 text-sm font-black shadow-lg shadow-blue-950/30">
                            {{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}

                            <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-slate-900 bg-emerald-400"></span>
                        </span>

                        <span class="min-w-0">
                            <span class="block truncate text-sm font-extrabold">
                                {{ $currentUser->name }}
                            </span>

                            <span class="mt-0.5 block truncate text-[10px] font-semibold text-slate-500">
                                {{ $currentUser->work_unit ?: 'Unit kerja belum diisi' }}
                            </span>
                        </span>
                    </div>

                    <div class="mt-3 flex items-center justify-between border-t border-white/[.07] pt-3">
                        <span class="text-[9px] font-black uppercase tracking-[.13em] text-slate-600">
                            {{ $roleLabel }}
                        </span>

                        <a
                            href="{{ route('profile.show') }}"
                            class="text-[10px] font-extrabold text-sky-300 transition hover:text-sky-200"
                        >
                            Lihat profil
                        </a>
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('logout') }}"
                    class="mt-3"
                >
                    @csrf

                    <button
                        type="submit"
                        class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-white/[.08] px-4 py-2.5 text-xs font-extrabold text-slate-400 transition hover:border-red-400/20 hover:bg-red-400/10 hover:text-red-300 focus-visible:ring-red-400/20"
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
                            <path d="m10 17 5-5-5-5M15 12H3M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>
                        </svg>

                        Keluar
                    </button>
                </form>
            </div>
        </aside>

        <div class="min-w-0">
            <header class="sticky top-0 z-20 flex h-19 items-center justify-between border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur-xl sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        id="sidebar-toggle"
                        type="button"
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-sky-200 hover:text-sky-700 lg:hidden"
                        aria-label="Buka menu"
                        aria-controls="sidebar"
                        aria-expanded="false"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            aria-hidden="true"
                        >
                            <path d="M4 7h16M4 12h16M4 17h16"/>
                        </svg>
                    </button>

                    <div class="min-w-0">
                        <p class="truncate text-[10px] font-black uppercase tracking-[.15em] text-sky-600">
                            {{ $eyebrow }}
                        </p>

                        <p class="mt-1 truncate text-sm font-black tracking-tight text-slate-950 sm:text-base">
                            {{ $header }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden text-right md:block">
                        <p class="text-[11px] font-extrabold text-slate-700">
                            {{ $displayNow->translatedFormat('l, d F Y') }}
                        </p>

                        <p class="mt-0.5 text-[10px] font-semibold text-slate-400">
                            {{ $roleLabel }}
                        </p>
                    </div>

                    <a
                        href="{{ route('profile.show') }}"
                        class="grid h-10 w-10 place-items-center rounded-xl bg-slate-950 text-sm font-black text-white shadow-sm transition hover:bg-sky-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-sky-200"
                        aria-label="Buka profil {{ $currentUser->name }}"
                    >
                        {{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}
                    </a>
                </div>
            </header>

            <main class="relative min-h-[calc(100vh-4.75rem)] overflow-hidden p-4 sm:p-6 lg:p-8">
                <div
                    class="pointer-events-none absolute inset-0 opacity-[.32] [background-image:radial-gradient(#cbd5e1_1px,transparent_1px)] [background-size:24px_24px] [mask-image:linear-gradient(to_bottom,black,transparent_38%)]"
                    aria-hidden="true"
                ></div>

                <div class="relative mx-auto max-w-[1500px]">
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

                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</body>
</html>

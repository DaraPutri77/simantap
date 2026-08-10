@props(['user'])

@php
    $notificationPreview = $user->notifications()
        ->latest()
        ->limit(6)
        ->get();
    $unreadCount = $user->unreadNotifications()->count();
@endphp

<details class="group relative">
    <summary
        class="relative grid h-10 w-10 cursor-pointer list-none place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-sky-200 hover:text-sky-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-sky-100 [&::-webkit-details-marker]:hidden"
        aria-label="Buka notifikasi"
    >
        <svg
            viewBox="0 0 24 24"
            class="h-5 w-5"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
            <path d="M10 21h4"/>
        </svg>

        @if ($unreadCount > 0)
            <span class="absolute -right-1 -top-1 min-w-5 rounded-full bg-red-600 px-1.5 py-0.5 text-center text-[9px] font-black leading-4 text-white ring-2 ring-white">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </summary>

    <div class="fixed inset-x-3 top-[4.75rem] z-50 max-h-[calc(100dvh-6rem)] overflow-y-auto overscroll-contain rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/15 lg:absolute lg:inset-x-auto lg:right-0 lg:top-auto lg:mt-3 lg:max-h-[min(70vh,32rem)] lg:w-[min(24rem,calc(100vw-2rem))]">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3.5">
            <div>
                <p class="text-sm font-black text-slate-950">Notifikasi</p>
                <p class="mt-0.5 text-[10px] font-bold text-slate-500">
                    {{ number_format($unreadCount, 0, ',', '.') }} belum dibaca
                </p>
            </div>

            <a
                href="{{ route('notifications.index') }}"
                class="text-[10px] font-black uppercase tracking-[.08em] text-sky-700 transition hover:text-sky-900"
            >
                Lihat semua
            </a>
        </div>

        <div class="max-h-[28rem] overflow-y-auto">
            @forelse ($notificationPreview as $notification)
                @php
                    $data = $notification->data;
                    $level = data_get($data, 'level', 'info');
                    $dotClass = match ($level) {
                        'success' => 'bg-emerald-500',
                        'warning' => 'bg-amber-500',
                        'danger' => 'bg-red-500',
                        default => 'bg-sky-500',
                    };
                    $displayCreatedAt = $notification->created_at?->copy()->timezone(
                        config('simantap.display_timezone', 'Asia/Jakarta'),
                    );
                @endphp

                <a
                    href="{{ route('notifications.open', $notification->id) }}"
                    class="block border-b border-slate-100 px-4 py-3.5 transition hover:bg-slate-50 {{ $notification->read_at === null ? 'bg-sky-50/45' : 'bg-white' }}"
                >
                    <div class="flex gap-3">
                        <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $dotClass }}"></span>
                        <span class="min-w-0">
                            <span class="block text-xs font-black text-slate-900">
                                {{ data_get($data, 'title', 'Notifikasi SIMANTAP') }}
                            </span>
                            <span class="mt-1 block text-[10px] font-semibold leading-5 text-slate-500">
                                Klik untuk melihat detail notifikasi.
                            </span>
                            <span class="mt-1.5 block text-[9px] font-bold uppercase tracking-[.08em] text-slate-400">
                                {{ $displayCreatedAt?->translatedFormat('d M Y H:i') ?? '-' }} WIB
                            </span>
                        </span>
                    </div>
                </a>
            @empty
                <div class="px-5 py-8 text-center">
                    <p class="text-xs font-extrabold text-slate-600">Belum ada notifikasi.</p>
                </div>
            @endforelse
        </div>
    </div>
</details>

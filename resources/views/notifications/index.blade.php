<x-layouts.app
    title="Notifikasi"
    header="Notifikasi"
    eyebrow="Aktivitas"
>
    <section class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Pusat Notifikasi</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Notifikasi SIMANTAP
            </h1>
            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-600">
                Pantau perubahan permintaan barang, peminjaman kendaraan, pengembalian, pemeliharaan, dan peringatan stok dari satu tempat.
            </p>
        </div>

        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="secondary-button sm:w-auto">
                    Tandai Semua Dibaca
                </button>
            </form>
        @endif
    </section>

    <section class="mt-6 grid grid-cols-2 gap-3 sm:max-w-xl">
        <article class="stat-card p-4 sm:p-5">
            <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">
                Belum Dibaca
            </p>
            <p class="mt-3 text-3xl font-black tracking-tight text-slate-950">
                {{ number_format($unreadCount, 0, ',', '.') }}
            </p>
        </article>
        <article class="stat-card p-4 sm:p-5">
            <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">
                Pada Filter
            </p>
            <p class="mt-3 text-3xl font-black tracking-tight text-slate-950">
                {{ number_format($notifications->total(), 0, ',', '.') }}
            </p>
        </article>
    </section>

    <section class="panel mt-6 p-4 sm:p-5">
        <div class="flex flex-wrap gap-2">
            @foreach ([
                'all' => 'Semua',
                'unread' => 'Belum Dibaca',
                'read' => 'Sudah Dibaca',
            ] as $value => $label)
                <a
                    href="{{ route('notifications.index', ['filter' => $value]) }}"
                    class="rounded-xl px-4 py-2 text-xs font-extrabold transition {{ $filter === $value ? 'bg-slate-950 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-700 hover:border-sky-200 hover:text-sky-700' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </section>

    <section class="mt-6 space-y-3">
        @forelse ($notifications as $notification)
            @php
                $data = $notification->data;
                $level = data_get($data, 'level', 'info');
                $accent = match ($level) {
                    'success' => 'bg-emerald-500',
                    'warning' => 'bg-amber-500',
                    'danger' => 'bg-red-500',
                    default => 'bg-sky-500',
                };
                $displayCreatedAt = $notification->created_at?->copy()->timezone(
                    config('simantap.display_timezone', 'Asia/Jakarta'),
                );
            @endphp

            <article class="panel overflow-hidden">
                <div class="flex gap-4 p-5 sm:p-6 {{ $notification->read_at === null ? 'bg-sky-50/35' : '' }}">
                    <span class="mt-1 h-3 w-3 shrink-0 rounded-full {{ $accent }}"></span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-sm font-black text-slate-950 sm:text-base">
                                        {{ data_get($data, 'title', 'Notifikasi SIMANTAP') }}
                                    </h2>
                                    @if ($notification->read_at === null)
                                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-[.08em] text-sky-800">
                                            Baru
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-2 text-sm font-medium leading-6 text-slate-600">
                                    {{ data_get($data, 'message', '-') }}
                                </p>
                                <p class="mt-2 text-[10px] font-bold uppercase tracking-[.08em] text-slate-400">
                                    {{ $displayCreatedAt?->translatedFormat('d M Y H:i') ?? '-' }} WIB
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-wrap gap-2">
                                @if ($notification->read_at === null)
                                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                        @csrf
                                        <button type="submit" class="secondary-button py-2 text-[10px] sm:w-auto">
                                            Tandai Dibaca
                                        </button>
                                    </form>
                                @endif

                                <a
                                    href="{{ route('notifications.open', $notification->id) }}"
                                    class="primary-button py-2 text-[10px] sm:w-auto"
                                >
                                    Buka
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <div class="empty-state panel">
                <p class="font-extrabold text-slate-700">
                    Tidak ada notifikasi pada filter ini.
                </p>
            </div>
        @endforelse
    </section>

    @if ($notifications->hasPages())
        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
</x-layouts.app>

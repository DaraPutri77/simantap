<x-layouts.app
    title="Audit Log"
    header="Audit Log"
    eyebrow="Kontrol Sistem"
>
    @php
        $presenter = \App\Support\AuditLogPresenter::class;
    @endphp

    <section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="eyebrow">Jejak Aktivitas</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Audit Log
            </h1>
            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-500">
                Telusuri aktivitas penting berdasarkan pelaku, modul, kejadian,
                request ID, dan waktu. Waktu ditampilkan dalam WIB agar sesuai
                dengan operasional kantor.
            </p>
        </div>
        <div class="rounded-2xl bg-slate-950 px-4 py-3 text-xs font-bold leading-5 text-slate-300 sm:max-w-sm">
            Catatan bersifat permanen dan hanya dapat dibaca. Perubahan atau
            penghapusan audit tidak disediakan oleh sistem.
        </div>
    </section>

    <section class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Aktivitas', 'value' => $summary['activities'], 'tone' => 'bg-sky-50 text-sky-700 ring-sky-100'],
            ['label' => 'Pelaku', 'value' => $summary['actors'], 'tone' => 'bg-violet-50 text-violet-700 ring-violet-100'],
            ['label' => 'Modul', 'value' => $summary['modules'], 'tone' => 'bg-emerald-50 text-emerald-700 ring-emerald-100'],
            ['label' => 'Otomatis Sistem', 'value' => $summary['system'], 'tone' => 'bg-slate-100 text-slate-700 ring-slate-200'],
        ] as $card)
            <article class="stat-card p-4 sm:p-5">
                <span class="inline-flex rounded-xl px-2.5 py-1 text-[10px] font-black uppercase tracking-[.12em] ring-1 ring-inset {{ $card['tone'] }}">
                    {{ $card['label'] }}
                </span>
                <p class="mt-4 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">
                    {{ number_format($card['value'], 0, ',', '.') }}
                </p>
            </article>
        @endforeach
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Riwayat Aktivitas</h2>
                <p class="panel-subtitle">{{ number_format($auditLogs->total(), 0, ',', '.') }} catatan ditemukan</p>
            </div>
        </div>

        <form
            method="GET"
            action="{{ route('audit-logs.index') }}"
            class="inventory-filter-grid"
        >
            <div>
                <label for="q" class="form-label">Pencarian</label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $filters['search'] }}"
                    class="form-input"
                    placeholder="Pelaku, event, IP, atau URL"
                >
            </div>
            <div>
                <label for="actor" class="form-label">Pelaku</label>
                <select id="actor" name="actor" class="form-input">
                    <option value="">Semua pelaku</option>
                    @foreach ($actors as $actor)
                        <option value="{{ $actor->id }}" @selected($filters['actorId'] === $actor->id)>
                            {{ $actor->name }} · {{ $actor->employee_number }}{{ $actor->trashed() ? ' · Arsip' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="module" class="form-label">Modul</label>
                <select id="module" name="module" class="form-input">
                    <option value="">Semua modul</option>
                    @foreach ($moduleOptions as $module)
                        <option value="{{ $module }}" @selected($filters['module'] === $module)>
                            {{ $presenter::moduleLabel($module) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="event" class="form-label">Kejadian</label>
                <select id="event" name="event" class="form-input">
                    <option value="">Semua kejadian</option>
                    @foreach ($eventOptions as $event)
                        <option value="{{ $event }}" @selected($filters['event'] === $event)>
                            {{ $presenter::eventLabel($event) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="method" class="form-label">Metode HTTP</label>
                <select id="method" name="method" class="form-input">
                    <option value="">Semua metode</option>
                    @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method)
                        <option value="{{ $method }}" @selected($filters['method'] === $method)>{{ $method }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="request_id" class="form-label">Request ID</label>
                <input
                    id="request_id"
                    name="request_id"
                    type="text"
                    value="{{ $filters['requestId'] }}"
                    class="form-input"
                    placeholder="UUID lengkap"
                >
            </div>
            <div>
                <label for="from" class="form-label">Dari Tanggal</label>
                <input id="from" name="from" type="date" value="{{ $filters['from'] }}" class="form-input">
            </div>
            <div>
                <label for="until" class="form-label">Sampai Tanggal</label>
                <input id="until" name="until" type="date" value="{{ $filters['until'] }}" class="form-input">
            </div>
            <div>
                <label for="per_page" class="form-label">Baris per Halaman</label>
                <select id="per_page" name="per_page" class="form-input">
                    @foreach ([15, 30, 50] as $size)
                        <option value="{{ $size }}" @selected($filters['perPage'] === $size)>{{ $size }} baris</option>
                    @endforeach
                </select>
            </div>
            <div class="inventory-filter-actions">
                <a href="{{ route('audit-logs.index') }}" class="secondary-button">Reset</a>
                <button type="submit" class="button-primary-inline">Terapkan</button>
            </div>
        </form>

        @if ($auditLogs->isEmpty())
            <div class="empty-state">
                Tidak ada aktivitas yang sesuai dengan filter.
            </div>
        @else
            <div class="hidden overflow-x-auto lg:block">
                <table class="data-table min-w-[1180px]">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Kejadian</th>
                            <th>Pelaku</th>
                            <th>Objek</th>
                            <th>Konteks Request</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($auditLogs as $auditLog)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <p class="font-extrabold text-slate-900">
                                        {{ $auditLog->created_at->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i:s') }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">WIB · #{{ $auditLog->id }}</p>
                                </td>
                                <td class="max-w-72">
                                    <span class="status-badge">{{ $presenter::moduleLabel($auditLog->module) }}</span>
                                    <p class="mt-2 font-extrabold text-slate-900">
                                        {{ $presenter::eventLabel($auditLog->event) }}
                                    </p>
                                    <p class="mt-1 break-all font-mono text-[11px] text-slate-500">{{ $auditLog->event }}</p>
                                </td>
                                <td>
                                    <p class="font-extrabold text-slate-900">
                                        {{ $auditLog->actor?->name ?: 'Otomatis sistem' }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $auditLog->actor?->employee_number ?: 'Tanpa akun pelaku' }}
                                        @if ($auditLog->actor?->trashed())
                                            · Akun diarsipkan
                                        @endif
                                    </p>
                                </td>
                                <td>
                                    <p class="font-extrabold text-slate-900">
                                        {{ $presenter::auditableLabel($auditLog->auditable_type) }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $presenter::recordLabel($auditLog) }}</p>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-[10px] font-black text-slate-700">
                                            {{ $auditLog->http_method ?: '—' }}
                                        </span>
                                        <span class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-[10px] font-bold text-slate-600">
                                            {{ $auditLog->ip_address ?: 'IP tidak tersedia' }}
                                        </span>
                                    </div>
                                    <p class="mt-2 max-w-72 truncate font-mono text-[10px] text-slate-500" title="{{ $auditLog->request_id }}">
                                        {{ $auditLog->request_id ?: 'Tanpa request ID' }}
                                    </p>
                                </td>
                                <td class="text-right">
                                    <a
                                        href="{{ route('audit-logs.show', $auditLog) }}"
                                        class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-3 py-2 text-xs font-black text-white transition hover:bg-sky-700"
                                    >
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 lg:hidden">
                @foreach ($auditLogs as $auditLog)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <span class="status-badge">{{ $presenter::moduleLabel($auditLog->module) }}</span>
                                <h3 class="mt-2 font-black text-slate-950">
                                    {{ $presenter::eventLabel($auditLog->event) }}
                                </h3>
                                <p class="mt-1 break-all font-mono text-[10px] text-slate-500">{{ $auditLog->event }}</p>
                            </div>
                            <span class="shrink-0 rounded-lg bg-slate-100 px-2 py-1 font-mono text-[10px] font-black text-slate-700">
                                {{ $auditLog->http_method ?: '—' }}
                            </span>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 rounded-2xl bg-slate-50 p-4 text-xs">
                            <div>
                                <dt class="font-bold text-slate-500">Waktu</dt>
                                <dd class="mt-1 font-extrabold text-slate-900">
                                    {{ $auditLog->created_at->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                                </dd>
                            </div>
                            <div>
                                <dt class="font-bold text-slate-500">Pelaku</dt>
                                <dd class="mt-1 font-extrabold text-slate-900">{{ $auditLog->actor?->name ?: 'Otomatis sistem' }}</dd>
                            </div>
                            <div>
                                <dt class="font-bold text-slate-500">Objek</dt>
                                <dd class="mt-1 font-extrabold text-slate-900">{{ $presenter::recordLabel($auditLog) }}</dd>
                            </div>
                            <div>
                                <dt class="font-bold text-slate-500">Alamat IP</dt>
                                <dd class="mt-1 font-mono font-bold text-slate-900">{{ $auditLog->ip_address ?: '—' }}</dd>
                            </div>
                        </dl>
                        <a href="{{ route('audit-logs.show', $auditLog) }}" class="secondary-button mt-4">Lihat Detail</a>
                    </article>
                @endforeach
            </div>

            @if ($auditLogs->hasPages())
                <div class="border-t border-slate-200 px-5 py-4 sm:px-6">
                    {{ $auditLogs->links() }}
                </div>
            @endif
        @endif
    </section>
</x-layouts.app>

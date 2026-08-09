<x-layouts.app
    title="Detail Audit Log"
    header="Detail Audit Log"
    eyebrow="Kontrol Sistem"
>
    @php
        $presenter = \App\Support\AuditLogPresenter::class;
        $actor = $auditLog->actor;
    @endphp

    <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-[0_24px_65px_rgba(15,23,42,.18)] sm:p-8">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_16%,rgba(14,165,233,.24),transparent_30%),radial-gradient(circle_at_88%_84%,rgba(37,99,235,.18),transparent_28%)]"></div>
        <div class="relative flex flex-col gap-6 xl:flex-row xl:items-center">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-sky-400/15 px-2.5 py-1 text-[10px] font-extrabold text-sky-200 ring-1 ring-inset ring-sky-300/20">
                        {{ $presenter::moduleLabel($auditLog->module) }}
                    </span>
                    <span class="rounded-full bg-emerald-400/15 px-2.5 py-1 text-[10px] font-extrabold text-emerald-200 ring-1 ring-inset ring-emerald-300/20">
                        Immutable
                    </span>
                </div>
                <h1 class="mt-3 break-words text-3xl font-black tracking-tight sm:text-4xl">
                    {{ $presenter::eventLabel($auditLog->event) }}
                </h1>
                <p class="mt-2 break-all font-mono text-xs text-slate-400">{{ $auditLog->event }}</p>
                <p class="mt-3 text-sm font-medium text-slate-300">
                    {{ $auditLog->created_at->copy()->timezone($displayTimezone)->translatedFormat('d F Y, H:i:s') }} WIB
                    · audit #{{ $auditLog->id }}
                </p>
            </div>
            <a href="{{ route('audit-logs.index') }}" class="button-secondary-dark">
                Kembali ke Audit Log
            </a>
        </div>
    </section>

    <div class="alert-warning mt-6" role="note">
        <div>
            <p class="font-black">Catatan ini hanya untuk penelusuran.</p>
            <p class="mt-1 text-xs leading-5">
                SIMANTAP tidak menyediakan aksi ubah atau hapus untuk audit log.
                Nilai kredensial dan tanda tangan mentah disensor dari tampilan.
            </p>
        </div>
    </div>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-6">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Perubahan Data</h2>
                        <p class="panel-subtitle">Perbandingan nilai sebelum dan sesudah aktivitas</p>
                    </div>
                </div>

                @if ($changes === [])
                    <div class="empty-state">
                        Aktivitas ini tidak mencatat perubahan nilai objek.
                    </div>
                @else
                    <div class="hidden overflow-x-auto md:block">
                        <table class="data-table min-w-[760px]">
                            <thead>
                                <tr>
                                    <th class="w-48">Field</th>
                                    <th>Nilai Sebelum</th>
                                    <th>Nilai Sesudah</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($changes as $change)
                                    <tr class="align-top">
                                        <td class="font-extrabold text-slate-900">{{ $change['field'] }}</td>
                                        <td class="max-w-md">
                                            @if ($change['hasOld'])
                                                <pre class="whitespace-pre-wrap break-words font-mono text-xs leading-5 text-slate-700">{{ $change['old'] }}</pre>
                                            @else
                                                <span class="text-xs italic text-slate-400">Tidak dicatat</span>
                                            @endif
                                        </td>
                                        <td class="max-w-md">
                                            @if ($change['hasNew'])
                                                <pre class="whitespace-pre-wrap break-words font-mono text-xs leading-5 text-slate-700">{{ $change['new'] }}</pre>
                                            @else
                                                <span class="text-xs italic text-slate-400">Tidak dicatat</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="divide-y divide-slate-100 md:hidden">
                        @foreach ($changes as $change)
                            <article class="p-5">
                                <h3 class="font-black text-slate-950">{{ $change['field'] }}</h3>
                                <div class="mt-3 grid gap-3">
                                    <div class="rounded-2xl bg-red-50/70 p-4">
                                        <p class="text-[10px] font-black uppercase tracking-[.12em] text-red-600">Sebelum</p>
                                        <pre class="mt-2 whitespace-pre-wrap break-words font-mono text-xs leading-5 text-slate-700">{{ $change['hasOld'] ? $change['old'] : 'Tidak dicatat' }}</pre>
                                    </div>
                                    <div class="rounded-2xl bg-emerald-50/70 p-4">
                                        <p class="text-[10px] font-black uppercase tracking-[.12em] text-emerald-700">Sesudah</p>
                                        <pre class="mt-2 whitespace-pre-wrap break-words font-mono text-xs leading-5 text-slate-700">{{ $change['hasNew'] ? $change['new'] : 'Tidak dicatat' }}</pre>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Konteks Request</h2>
                        <p class="panel-subtitle">Informasi teknis untuk korelasi dan investigasi</p>
                    </div>
                </div>
                <dl class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
                    <div class="rounded-2xl bg-slate-50 p-4 sm:col-span-2">
                        <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Request ID</dt>
                        <dd class="mt-2 break-all font-mono text-xs font-bold text-slate-900">{{ $auditLog->request_id ?: 'Tidak tersedia' }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Metode HTTP</dt>
                        <dd class="mt-2 font-mono text-sm font-black text-slate-900">{{ $auditLog->http_method ?: 'Tidak tersedia' }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Alamat IP</dt>
                        <dd class="mt-2 break-all font-mono text-sm font-black text-slate-900">{{ $auditLog->ip_address ?: 'Tidak tersedia' }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4 sm:col-span-2">
                        <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">URL Tersanitasi</dt>
                        <dd class="mt-2 break-all font-mono text-xs leading-5 text-slate-700">{{ $safeUrl ?: 'Tidak tersedia' }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4 sm:col-span-2">
                        <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">User Agent</dt>
                        <dd class="mt-2 break-words text-xs leading-5 text-slate-700">{{ $auditLog->user_agent ?: 'Tidak tersedia' }}</dd>
                    </div>
                </dl>
            </article>
        </div>

        <aside class="space-y-6">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Pelaku</h2>
                        <p class="panel-subtitle">Akun yang memicu aktivitas</p>
                    </div>
                </div>
                <div class="p-5 sm:p-6">
                    <div class="grid h-12 w-12 place-items-center rounded-2xl bg-sky-100 text-lg font-black text-sky-700">
                        {{ mb_strtoupper(mb_substr($actor?->name ?: 'S', 0, 1)) }}
                    </div>
                    <p class="mt-4 text-lg font-black text-slate-950">{{ $actor?->name ?: 'Otomatis sistem' }}</p>
                    <p class="mt-1 text-sm font-semibold text-slate-500">{{ $actor?->employee_number ?: 'Tanpa akun pelaku' }}</p>
                    @if ($actor)
                        <dl class="mt-5 space-y-3 border-t border-slate-100 pt-5 text-sm">
                            <div>
                                <dt class="text-xs font-bold text-slate-500">Unit kerja</dt>
                                <dd class="mt-1 font-extrabold text-slate-900">{{ $actor->work_unit ?: 'Tidak tersedia' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-bold text-slate-500">Jabatan</dt>
                                <dd class="mt-1 font-extrabold text-slate-900">{{ $actor->position ?: 'Tidak tersedia' }}</dd>
                            </div>
                        </dl>
                        @if ($actor->trashed())
                            <p class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">Akun telah diarsipkan.</p>
                        @endif
                    @endif
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Objek Audit</h2>
                        <p class="panel-subtitle">Referensi data yang terdampak</p>
                    </div>
                </div>
                <dl class="space-y-4 p-5 text-sm sm:p-6">
                    <div>
                        <dt class="text-xs font-bold text-slate-500">Jenis objek</dt>
                        <dd class="mt-1 font-extrabold text-slate-900">{{ $presenter::auditableLabel($auditLog->auditable_type) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-slate-500">Identitas</dt>
                        <dd class="mt-1 break-words font-extrabold text-slate-900">{{ $presenter::recordLabel($auditLog) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-slate-500">ID internal</dt>
                        <dd class="mt-1 font-mono font-bold text-slate-900">{{ $auditLog->auditable_id ?: 'Tidak tersedia' }}</dd>
                    </div>
                </dl>
            </article>
        </aside>
    </section>
</x-layouts.app>

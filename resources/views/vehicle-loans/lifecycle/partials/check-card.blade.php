@php
    $check = $check ?? null;
    $title = $title ?? 'Pemeriksaan Kondisi';
    $displayTimezone = $displayTimezone ?? config('simantap.display_timezone', 'Asia/Jakarta');
@endphp

@if ($check)
    <section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[.12em] text-slate-500">
                    {{ $title }}
                </p>
                <p class="mt-2 text-lg font-black text-slate-950">
                    {{ $check->overall_condition->label() }}
                </p>
            </div>
            <div class="text-xs font-semibold text-slate-500 sm:text-right">
                <p>{{ $check->checker?->name ?: 'Petugas' }}</p>
                <p class="mt-1">
                    {{ $check->checked_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                </p>
            </div>
        </div>

        <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl bg-slate-50 p-3">
                <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Odometer</dt>
                <dd class="mt-1 text-sm font-black text-slate-900">
                    {{ number_format((float) $check->odometer, 1, ',', '.') }} km
                </dd>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
                <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Bahan Bakar</dt>
                <dd class="mt-1 text-sm font-black text-slate-900">{{ $check->fuel_level }}%</dd>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
                <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Konfirmasi Peminjam</dt>
                <dd class="mt-1 text-sm font-black text-slate-900">
                    {{ $check->borrower_confirmed_at ? 'Sudah' : '-' }}
                </dd>
            </div>
        </dl>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach ([
                'Bodi' => $check->body_condition,
                'Mesin' => $check->engine_condition,
                'Ban' => $check->tire_condition,
                'Kelengkapan' => $check->equipment_condition,
            ] as $label => $value)
                <div class="rounded-xl border border-slate-200 p-3">
                    <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">{{ $label }}</p>
                    <p class="mt-1 whitespace-pre-line text-xs font-semibold leading-5 text-slate-700">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        @if ($check->damage_notes)
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
                <p class="text-[10px] font-black uppercase tracking-[.12em] text-amber-800">Catatan Temuan</p>
                <p class="mt-1 whitespace-pre-line text-xs font-semibold leading-5 text-amber-950">{{ $check->damage_notes }}</p>
            </div>
        @endif

        @if ($check->attachments->isNotEmpty())
            <div class="mt-4">
                <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Bukti Foto</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($check->attachments as $attachment)
                        <a
                            href="{{ route('vehicle-loan-lifecycle.evidence', [$vehicleLoan, $attachment]) }}"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-black text-sky-800 transition hover:bg-sky-100"
                        >
                            {{ $attachment->file_category->label() }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
@endif

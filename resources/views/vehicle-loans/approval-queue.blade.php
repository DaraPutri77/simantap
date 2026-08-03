<x-layouts.app
    title="Approval Peminjaman Kendaraan"
    header="Approval Peminjaman"
    eyebrow="Kendaraan"
>
    <section class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Antrean Administrator</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Approval Peminjaman Kendaraan
            </h1>
            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-600">
                Prioritaskan jadwal terdekat, periksa kelayakan kendaraan, lalu
                setujui atau tolak dengan alasan yang dapat ditelusuri.
            </p>
        </div>
        <a href="{{ route('vehicle-loans.index') }}" class="secondary-button sm:w-auto">
            Semua Peminjaman
        </a>
    </section>

    <section class="mt-6 grid grid-cols-3 gap-3">
        @foreach ([
            ['label' => 'Total Antrean', 'value' => $summary['total'], 'tone' => 'bg-sky-100 text-sky-900 ring-sky-300'],
            ['label' => 'Baru Diajukan', 'value' => $summary['submitted'], 'tone' => 'bg-blue-100 text-blue-900 ring-blue-300'],
            ['label' => 'Sedang Diperiksa', 'value' => $summary['under_review'], 'tone' => 'bg-amber-100 text-amber-950 ring-amber-300'],
        ] as $card)
            <article class="stat-card p-4 sm:p-5">
                <span class="inline-flex rounded-xl px-2.5 py-1 text-[10px] font-black uppercase tracking-[.1em] ring-1 ring-inset {{ $card['tone'] }}">
                    {{ $card['label'] }}
                </span>
                <p class="mt-4 text-3xl font-black text-slate-950">
                    {{ number_format($card['value'], 0, ',', '.') }}
                </p>
            </article>
        @endforeach
    </section>

    <section class="panel mt-6">
        <form
            method="GET"
            action="{{ route('vehicle-loans.approval-queue') }}"
            class="inventory-filter-grid"
        >
            <div>
                <label for="q" class="form-label">Cari Antrean</label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $filters['search'] }}"
                    class="form-input"
                    placeholder="Nomor, pegawai, kendaraan, tujuan"
                >
            </div>
            <div>
                <label for="stage" class="form-label">Tahap</label>
                <select id="stage" name="stage" class="form-input">
                    <option value="">Semua tahap</option>
                    @foreach ($stageOptions as $stageOption)
                        <option value="{{ $stageOption->value }}" @selected($filters['stage'] === $stageOption->value)>
                            {{ $stageOption->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="inventory-filter-actions">
                <a href="{{ route('vehicle-loans.approval-queue') }}" class="secondary-button">
                    Reset
                </a>
                <button type="submit" class="button-primary-inline">Terapkan</button>
            </div>
        </form>

        @if ($vehicleLoans->isEmpty())
            <div class="empty-state">
                <p class="font-extrabold text-slate-700">Antrean approval kosong.</p>
                <p class="mt-1">Tidak ada pengajuan yang perlu diproses.</p>
            </div>
        @else
            <div class="grid gap-4 p-4 lg:grid-cols-2">
                @foreach ($vehicleLoans as $vehicleLoan)
                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-lg font-black text-slate-950">
                                    {{ $vehicleLoan->loan_number }}
                                </p>
                                <p class="mt-1 text-xs font-semibold text-slate-600">
                                    {{ $vehicleLoan->borrower_name_snapshot }} ·
                                    {{ $vehicleLoan->work_unit_snapshot ?: '-' }}
                                </p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-black ring-1 ring-inset {{ $vehicleLoan->status->badgeClasses() }}">
                                {{ $vehicleLoan->status->label() }}
                            </span>
                        </div>

                        <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Kendaraan</dt>
                                <dd class="mt-1 text-sm font-bold text-slate-900">
                                    {{ $vehicleLoan->license_plate_snapshot }} ·
                                    {{ $vehicleLoan->vehicle_name_snapshot }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Tujuan</dt>
                                <dd class="mt-1 text-sm font-bold text-slate-900">
                                    {{ $vehicleLoan->destination }}
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Jadwal WIB</dt>
                                <dd class="mt-1 text-sm font-semibold leading-6 text-slate-700">
                                    {{ $vehicleLoan->planned_start_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }}
                                    –
                                    {{ $vehicleLoan->planned_end_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                                </dd>
                            </div>
                        </dl>

                        <a
                            href="{{ route('vehicle-loans.show', $vehicleLoan) }}"
                            class="primary-button mt-5"
                        >
                            Periksa Pengajuan
                        </a>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-200 p-4">
                {{ $vehicleLoans->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>

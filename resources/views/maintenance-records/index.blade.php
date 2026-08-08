<x-layouts.app
    title="Pemeliharaan Kendaraan"
    header="Pemeliharaan Kendaraan"
    eyebrow="Kendaraan"
>
    <section class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Kendali Pemeliharaan</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Pemeliharaan Kendaraan
            </h1>
            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-600">
                Kelola laporan kerusakan, tindak lanjut masalah pengembalian, pengerjaan, bukti digital, biaya, dan hasil akhir kendaraan secara tertelusur.
            </p>
        </div>
        @can('create', \App\Models\MaintenanceRecord::class)
            <a href="{{ route('maintenance-records.create') }}" class="primary-button sm:w-auto">
                Tambah Pemeliharaan
            </a>
        @endcan
    </section>

    @if (session('status'))
        <div class="alert-success mt-6">
            <strong>Berhasil.</strong>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Dilaporkan', 'value' => $summary['reported']],
            ['label' => 'Disetujui', 'value' => $summary['approved']],
            ['label' => 'Dalam Pengerjaan', 'value' => $summary['in_progress']],
            ['label' => 'Tindakan Lanjutan', 'value' => $summary['further_action']],
        ] as $card)
            <article class="stat-card p-4 sm:p-5">
                <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">
                    {{ $card['label'] }}
                </p>
                <p class="mt-3 text-3xl font-black tracking-tight text-slate-950">
                    {{ number_format($card['value'], 0, ',', '.') }}
                </p>
            </article>
        @endforeach
    </section>

    <section class="panel mt-6 p-5 sm:p-6">
        <form method="GET" class="grid gap-4 lg:grid-cols-[1fr_220px_160px_auto]">
            <div>
                <label for="q" class="form-label">Pencarian</label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ request('q') }}"
                    class="form-input"
                    placeholder="Nomor pemeliharaan, kendaraan, jenis, atau keluhan"
                >
            </div>
            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua status</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="year" class="form-label">Tahun</label>
                <input
                    id="year"
                    name="year"
                    type="number"
                    min="2020"
                    max="2100"
                    value="{{ request('year') }}"
                    class="form-input"
                    placeholder="2026"
                >
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="primary-button">Terapkan</button>
                <a href="{{ route('maintenance-records.index') }}" class="secondary-button">Reset</a>
            </div>
        </form>
    </section>

    <section class="mt-6 space-y-4">
        @forelse ($records as $record)
            <article class="panel p-5 sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-black text-slate-950">
                                {{ $record->maintenance_number }}
                            </h2>
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-800 ring-1 ring-inset ring-slate-300">
                                {{ $record->status->label() }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm font-bold text-slate-800">
                            {{ $record->vehicle_snapshot }}
                        </p>
                        <p class="mt-1 text-xs font-semibold text-slate-600">
                            {{ $record->maintenance_type }} · Dilaporkan {{ $record->reported_date->translatedFormat('d M Y') }}
                        </p>
                        @if ($record->sourceVehicleLoan)
                            <p class="mt-2 text-xs font-bold text-red-700">
                                Sumber masalah pengembalian: {{ $record->sourceVehicleLoan->loan_number }}
                            </p>
                        @endif
                    </div>
                    <a href="{{ route('maintenance-records.show', $record) }}" class="secondary-button sm:w-auto">
                        Detail
                    </a>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Pelapor</p>
                        <p class="mt-1 text-xs font-bold text-slate-900">{{ $record->reporter?->name ?: '-' }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Penanggung Jawab</p>
                        <p class="mt-1 text-xs font-bold text-slate-900">{{ $record->handler?->name ?: '-' }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Status Kendaraan</p>
                        <p class="mt-1 text-xs font-bold text-slate-900">{{ $record->vehicle?->status?->label() ?: '-' }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Biaya</p>
                        <p class="mt-1 text-xs font-bold text-slate-900">
                            {{ $record->cost !== null ? 'Rp '.number_format((float) $record->cost, 0, ',', '.') : '-' }}
                        </p>
                    </div>
                </div>
            </article>
        @empty
            <div class="empty-state panel">
                <p class="font-extrabold text-slate-700">Belum ada data pemeliharaan pada filter ini.</p>
            </div>
        @endforelse
    </section>

    @if ($records->hasPages())
        <div class="mt-6">{{ $records->links() }}</div>
    @endif
</x-layouts.app>

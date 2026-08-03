<x-layouts.app
    title="Kendaraan Operasional"
    header="Kendaraan Operasional"
    eyebrow="Kendaraan"
>
    <section class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Master Kendaraan</p>

            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Daftar Kendaraan Operasional
            </h1>

            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-600">
                {{ $canManage
                    ? 'Kelola identitas, status operasional, odometer, dokumen, lokasi, dan penanggung jawab kendaraan dinas.'
                    : 'Lihat kendaraan dinas beserta status ketersediaan dan informasi operasional terbarunya.' }}
            </p>
        </div>

        @if ($canManage)
            <a href="{{ route('vehicles.create') }}" class="button-primary-inline">
                Tambah Kendaraan
            </a>
        @endif
    </section>

    <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Total Kendaraan', 'value' => $summary['total'], 'tone' => 'bg-sky-50 text-sky-700 ring-sky-100'],
            ['label' => 'Tersedia', 'value' => $summary['available'], 'tone' => 'bg-emerald-50 text-emerald-700 ring-emerald-100'],
            ['label' => 'Tidak Tersedia', 'value' => $summary['unavailable'], 'tone' => 'bg-amber-50 text-amber-800 ring-amber-100'],
            ['label' => 'STNK Perlu Perhatian', 'value' => $summary['registration_attention'], 'tone' => 'bg-red-50 text-red-700 ring-red-100'],
        ] as $card)
            <article class="stat-card p-4 sm:p-5">
                <span class="inline-flex rounded-xl px-2.5 py-1 text-[10px] font-black uppercase tracking-[.12em] ring-1 ring-inset {{ $card['tone'] }}">
                    {{ $card['label'] }}
                </span>

                <p class="mt-4 text-3xl font-black tracking-tight text-slate-950">
                    {{ number_format($card['value'], 0, ',', '.') }}
                </p>
            </article>
        @endforeach
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Inventaris Kendaraan</h2>
                <p class="panel-subtitle">
                    {{ $vehicles->total() }} kendaraan ditemukan
                </p>
            </div>
        </div>

        <form
            method="GET"
            action="{{ route('vehicles.index') }}"
            class="inventory-filter-grid"
        >
            <div>
                <label for="q" class="form-label">Cari Kendaraan</label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $filters['search'] }}"
                    class="form-input"
                    placeholder="Kode, polisi, merek, lokasi"
                >
            </div>

            <div>
                <label for="status" class="form-label">Status Operasional</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua status</option>
                    @foreach ($statusOptions as $statusOption)
                        <option
                            value="{{ $statusOption->value }}"
                            @selected($filters['status'] === $statusOption->value)
                        >
                            {{ $statusOption->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="registration" class="form-label">Masa Berlaku STNK</label>
                <select id="registration" name="registration" class="form-input">
                    <option value="">Semua dokumen</option>
                    <option value="valid" @selected($filters['registration'] === 'valid')>
                        Berlaku lebih dari 30 hari
                    </option>
                    <option value="expiring" @selected($filters['registration'] === 'expiring')>
                        Habis dalam 30 hari
                    </option>
                    <option value="expired" @selected($filters['registration'] === 'expired')>
                        Sudah kedaluwarsa
                    </option>
                    <option value="missing" @selected($filters['registration'] === 'missing')>
                        Belum diisi
                    </option>
                </select>
            </div>

            @if ($canManage)
                <div>
                    <label for="active" class="form-label">Status Master</label>
                    <select id="active" name="active" class="form-input">
                        <option value="">Semua data</option>
                        <option value="active" @selected($filters['active'] === 'active')>
                            Aktif
                        </option>
                        <option value="inactive" @selected($filters['active'] === 'inactive')>
                            Nonaktif
                        </option>
                    </select>
                </div>
            @endif

            <div class="inventory-filter-actions">
                <button type="submit" class="button-primary-inline">
                    Terapkan
                </button>
                <a href="{{ route('vehicles.index') }}" class="secondary-button">
                    Reset
                </a>
            </div>
        </form>

        @if ($vehicles->isEmpty())
            <div class="empty-state">
                Tidak ada kendaraan yang sesuai dengan filter.
            </div>
        @else
            <div class="hidden overflow-x-auto lg:block">
                <table class="data-table min-w-[1050px]">
                    <thead>
                        <tr>
                            <th>Kendaraan</th>
                            <th>Nomor Polisi</th>
                            <th>Odometer</th>
                            <th>Masa Berlaku STNK</th>
                            <th>Status</th>
                            <th>Lokasi</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vehicles as $vehicle)
                            @php
                                $registrationState = $vehicle->registrationState($today);
                                $statusTone = match ($vehicle->status) {
                                    \App\Enums\VehicleStatus::Available => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    \App\Enums\VehicleStatus::Reserved => 'bg-sky-50 text-sky-700 ring-sky-200',
                                    \App\Enums\VehicleStatus::Borrowed => 'bg-blue-50 text-blue-700 ring-blue-200',
                                    \App\Enums\VehicleStatus::Inspection => 'bg-amber-50 text-amber-800 ring-amber-200',
                                    \App\Enums\VehicleStatus::Maintenance => 'bg-orange-50 text-orange-800 ring-orange-200',
                                    \App\Enums\VehicleStatus::Damaged => 'bg-red-50 text-red-700 ring-red-200',
                                    \App\Enums\VehicleStatus::Inactive => 'bg-slate-100 text-slate-600 ring-slate-200',
                                };
                                $registrationTone = match ($registrationState) {
                                    'valid' => 'text-emerald-700',
                                    'expiring' => 'text-amber-800',
                                    'expired' => 'text-red-700',
                                    default => 'text-slate-500',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-2xl bg-slate-100 text-xs font-black text-slate-500 ring-1 ring-slate-200">
                                            @if ($vehicle->image_path)
                                                <img
                                                    src="{{ asset('storage/'.$vehicle->image_path) }}"
                                                    alt="Foto {{ $vehicle->displayName() }}"
                                                    class="h-full w-full object-cover"
                                                >
                                            @else
                                                {{ mb_strtoupper(mb_substr($vehicle->brand, 0, 1)) }}
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="max-w-56 truncate font-black text-slate-950">
                                                {{ $vehicle->displayName() }}
                                            </p>
                                            <p class="mt-1 text-xs font-bold text-sky-700">
                                                {{ $vehicle->vehicle_code }}
                                                @if ($vehicle->year)
                                                    · {{ $vehicle->year }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="inline-flex rounded-lg bg-slate-950 px-3 py-1.5 text-xs font-black tracking-[.08em] text-white">
                                        {{ $vehicle->license_plate }}
                                    </span>
                                </td>
                                <td class="font-extrabold text-slate-800">
                                    {{ number_format((float) $vehicle->current_odometer, 1, ',', '.') }} km
                                </td>
                                <td>
                                    <p class="font-extrabold {{ $registrationTone }}">
                                        {{ $vehicle->registration_expiry_date?->translatedFormat('d M Y') ?: 'Belum diisi' }}
                                    </p>
                                    @if ($registrationState === 'expired')
                                        <p class="mt-1 text-xs font-bold text-red-600">Kedaluwarsa</p>
                                    @elseif ($registrationState === 'expiring')
                                        <p class="mt-1 text-xs font-bold text-amber-700">Segera berakhir</p>
                                    @endif
                                </td>
                                <td>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-extrabold ring-1 ring-inset {{ $statusTone }}">
                                        {{ $vehicle->status->label() }}
                                    </span>
                                    @if (! $vehicle->is_active)
                                        <p class="mt-1 text-[10px] font-bold text-slate-500">Master nonaktif</p>
                                    @endif
                                </td>
                                <td>
                                    <p class="max-w-48 truncate font-semibold text-slate-700">
                                        {{ $vehicle->storage_location ?: 'Belum diisi' }}
                                    </p>
                                </td>
                                <td class="text-right">
                                    <a
                                        href="{{ route('vehicles.show', $vehicle) }}"
                                        class="text-xs font-black text-sky-700 hover:text-sky-900"
                                    >
                                        Lihat Detail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-200 lg:hidden">
                @foreach ($vehicles as $vehicle)
                    @php
                        $registrationState = $vehicle->registrationState($today);
                        $statusTone = match ($vehicle->status) {
                            \App\Enums\VehicleStatus::Available => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                            \App\Enums\VehicleStatus::Reserved => 'bg-sky-50 text-sky-700 ring-sky-200',
                            \App\Enums\VehicleStatus::Borrowed => 'bg-blue-50 text-blue-700 ring-blue-200',
                            \App\Enums\VehicleStatus::Inspection => 'bg-amber-50 text-amber-800 ring-amber-200',
                            \App\Enums\VehicleStatus::Maintenance => 'bg-orange-50 text-orange-800 ring-orange-200',
                            \App\Enums\VehicleStatus::Damaged => 'bg-red-50 text-red-700 ring-red-200',
                            \App\Enums\VehicleStatus::Inactive => 'bg-slate-100 text-slate-600 ring-slate-200',
                        };
                    @endphp
                    <article class="p-5">
                        <div class="flex items-start gap-4">
                            <div class="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-2xl bg-slate-100 text-sm font-black text-slate-500 ring-1 ring-slate-200">
                                @if ($vehicle->image_path)
                                    <img
                                        src="{{ asset('storage/'.$vehicle->image_path) }}"
                                        alt="Foto {{ $vehicle->displayName() }}"
                                        class="h-full w-full object-cover"
                                    >
                                @else
                                    {{ mb_strtoupper(mb_substr($vehicle->brand, 0, 1)) }}
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-base font-black text-slate-950">
                                    {{ $vehicle->displayName() }}
                                </p>
                                <p class="mt-1 text-xs font-bold text-sky-700">
                                    {{ $vehicle->vehicle_code }} · {{ $vehicle->license_plate }}
                                </p>
                                <span class="mt-3 inline-flex rounded-full px-2.5 py-1 text-[11px] font-extrabold ring-1 ring-inset {{ $statusTone }}">
                                    {{ $vehicle->status->label() }}
                                </span>
                            </div>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 rounded-2xl bg-slate-50 p-4 text-xs">
                            <div>
                                <dt class="font-bold text-slate-500">Odometer</dt>
                                <dd class="mt-1 font-black text-slate-800">
                                    {{ number_format((float) $vehicle->current_odometer, 1, ',', '.') }} km
                                </dd>
                            </div>
                            <div>
                                <dt class="font-bold text-slate-500">STNK</dt>
                                <dd class="mt-1 font-black {{ in_array($registrationState, ['expired', 'expiring'], true) ? 'text-red-700' : 'text-slate-800' }}">
                                    {{ $vehicle->registration_expiry_date?->translatedFormat('d M Y') ?: 'Belum diisi' }}
                                </dd>
                            </div>
                        </dl>
                        <a
                            href="{{ route('vehicles.show', $vehicle) }}"
                            class="secondary-button mt-4"
                        >
                            Lihat Detail
                        </a>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-200 px-5 py-4">
                {{ $vehicles->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>

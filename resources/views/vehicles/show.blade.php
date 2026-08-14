@php
    $statusTone = match ($vehicle->status) {
        \App\Enums\VehicleStatus::Available => 'bg-emerald-400/15 text-emerald-200 ring-emerald-300/20',
        \App\Enums\VehicleStatus::Reserved => 'bg-sky-400/15 text-sky-200 ring-sky-300/20',
        \App\Enums\VehicleStatus::Borrowed => 'bg-blue-400/15 text-blue-200 ring-blue-300/20',
        \App\Enums\VehicleStatus::Inspection => 'bg-amber-400/15 text-amber-200 ring-amber-300/20',
        \App\Enums\VehicleStatus::Maintenance => 'bg-orange-400/15 text-orange-200 ring-orange-300/20',
        \App\Enums\VehicleStatus::Damaged => 'bg-red-400/15 text-red-200 ring-red-300/20',
        \App\Enums\VehicleStatus::Inactive => 'bg-slate-400/15 text-slate-300 ring-slate-300/20',
    };
    $registrationState = $vehicle->registrationState($today);
    $registrationMeta = match ($registrationState) {
        'valid' => ['label' => 'Dokumen berlaku', 'tone' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'expiring' => ['label' => 'Segera berakhir', 'tone' => 'bg-amber-50 text-amber-800 ring-amber-200'],
        'expired' => ['label' => 'Kedaluwarsa', 'tone' => 'bg-red-50 text-red-700 ring-red-200'],
        default => ['label' => 'Belum diisi', 'tone' => 'bg-slate-100 text-slate-600 ring-slate-200'],
    };
@endphp

<x-layouts.app
    title="Detail Kendaraan"
    header="Detail Kendaraan"
    eyebrow="Kendaraan"
>
    @if ($errors->any())
        <div class="alert-danger mb-6" role="alert">
            <div>
                <p class="font-extrabold">Tindakan belum dapat diproses.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-[0_24px_65px_rgba(15,23,42,.18)] sm:p-8">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_16%,rgba(14,165,233,.24),transparent_30%),radial-gradient(circle_at_88%_84%,rgba(37,99,235,.18),transparent_28%)]"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center">
            <div class="grid h-24 w-24 shrink-0 place-items-center overflow-hidden rounded-3xl bg-white/10 text-3xl font-black ring-1 ring-white/10 sm:h-28 sm:w-28">
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
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-extrabold uppercase tracking-[.16em] text-sky-300">
                        {{ $vehicle->vehicle_code }}
                    </p>
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold ring-1 ring-inset {{ $statusTone }}">
                        {{ $vehicle->status->label() }}
                    </span>
                    @if (! $vehicle->is_active)
                        <span class="rounded-full bg-slate-400/15 px-2.5 py-1 text-[10px] font-extrabold text-slate-300 ring-1 ring-inset ring-slate-300/20">
                            Master Nonaktif
                        </span>
                    @endif
                </div>
                <h1 class="mt-2 break-words text-3xl font-black tracking-tight sm:text-4xl">
                    {{ $vehicle->displayName() }}
                </h1>
                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm font-bold text-slate-300">
                    <span class="rounded-lg bg-white px-3 py-1.5 text-xs font-black tracking-[.1em] text-slate-950">
                        {{ $vehicle->license_plate }}
                    </span>
                    @if ($vehicle->year)
                        <span>{{ $vehicle->year }}</span>
                    @endif
                    @if ($vehicle->color)
                        <span>· {{ $vehicle->color }}</span>
                    @endif
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:flex">
                <a href="{{ route('vehicles.index') }}" class="button-secondary-dark">
                    Kembali
                </a>
                @if ($canManage)
                    <a href="{{ route('vehicles.control-card', $vehicle) }}" class="button-secondary-dark">
                        Kartu Kendali
                    </a>
                    <a href="{{ route('vehicles.edit', $vehicle) }}" class="button-primary-inline">
                        Edit Kendaraan
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-6">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Ringkasan Operasional</h2>
                        <p class="panel-subtitle">Status utama kendaraan saat ini</p>
                    </div>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-3 sm:p-6">
                    <div class="rounded-2xl bg-sky-50 p-4 text-sky-800">
                        <p class="text-[10px] font-black uppercase tracking-[.12em] opacity-70">Odometer</p>
                        <p class="mt-2 text-2xl font-black">
                            {{ number_format((float) $vehicle->current_odometer, 1, ',', '.') }}
                        </p>
                        <p class="mt-1 text-xs font-bold">kilometer</p>
                    </div>
                    <div class="rounded-2xl bg-emerald-50 p-4 text-emerald-800">
                        <p class="text-[10px] font-black uppercase tracking-[.12em] opacity-70">Riwayat Peminjaman</p>
                        <p class="mt-2 text-2xl font-black">
                            {{ number_format($vehicle->vehicle_loans_count, 0, ',', '.') }}
                        </p>
                        <p class="mt-1 text-xs font-bold">transaksi</p>
                    </div>
                    <div class="rounded-2xl bg-amber-50 p-4 text-amber-900">
                        <p class="text-[10px] font-black uppercase tracking-[.12em] opacity-70">Riwayat Pemeliharaan</p>
                        <p class="mt-2 text-2xl font-black">
                            {{ number_format($vehicle->maintenance_records_count, 0, ',', '.') }}
                        </p>
                        <p class="mt-1 text-xs font-bold">catatan</p>
                    </div>
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Identitas Teknis</h2>
                        <p class="panel-subtitle">Informasi registrasi kendaraan</p>
                    </div>
                </div>
                <dl class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                    @foreach ([
                        'Kode kendaraan' => $vehicle->vehicle_code,
                        'Nomor polisi' => $vehicle->license_plate,
                        'Merek' => $vehicle->brand,
                        'Tipe / model' => $vehicle->model,
                        'Tahun' => $vehicle->year ?: 'Belum diisi',
                        'Warna' => $vehicle->color ?: 'Belum diisi',
                        'Nomor rangka' => $vehicle->chassis_number ?: 'Belum diisi',
                        'Nomor mesin' => $vehicle->engine_number ?: 'Belum diisi',
                    ] as $label => $value)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <dt class="text-xs font-bold text-slate-500">{{ $label }}</dt>
                            <dd class="mt-2 break-words text-sm font-black text-slate-900">
                                {{ $value }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </article>

            @if ($vehicle->notes)
                <article class="panel p-5 sm:p-6">
                    <h2 class="panel-title">Catatan Kendaraan</h2>
                    <p class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-600">{{ $vehicle->notes }}</p>
                </article>
            @endif
        </div>

        <aside class="space-y-6">
            @include('qr-codes.preview', [
                'entityLabel' => 'kendaraan',
                'svgDownloadUrl' => route('qr-codes.vehicle.svg', $vehicle),
                'labelDownloadUrl' => route('qr-codes.vehicle.label', $vehicle),
            ])

            <article class="panel p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="panel-title">Dokumen STNK</h2>
                        <p class="mt-1 text-xs font-medium text-slate-500">Masa berlaku registrasi</p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold ring-1 ring-inset {{ $registrationMeta['tone'] }}">
                        {{ $registrationMeta['label'] }}
                    </span>
                </div>
                <p class="mt-5 text-2xl font-black tracking-tight text-slate-950">
                    {{ $vehicle->registration_expiry_date?->translatedFormat('d F Y') ?: 'Belum diisi' }}
                </p>
                @if ($registrationState === 'expired')
                    <div class="alert-danger mt-4">
                        STNK telah kedaluwarsa. Kendaraan perlu ditinjau sebelum digunakan.
                    </div>
                @elseif ($registrationState === 'expiring')
                    <div class="alert-warning mt-4">
                        Masa berlaku STNK berakhir dalam 30 hari.
                    </div>
                @endif
            </article>

            <article class="panel p-5 sm:p-6">
                <h2 class="panel-title">Penempatan dan Pengelola</h2>
                <dl class="mt-5 space-y-4 text-sm">
                    <div>
                        <dt class="text-xs font-bold text-slate-500">Lokasi penyimpanan</dt>
                        <dd class="mt-1 font-extrabold text-slate-800">
                            {{ $vehicle->storage_location ?: 'Belum diisi' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-slate-500">Penanggung jawab</dt>
                        <dd class="mt-1 font-extrabold text-slate-800">
                            {{ $vehicle->responsible_person ?: 'Belum diisi' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-slate-500">Terakhir diperbarui</dt>
                        <dd class="mt-1 font-extrabold text-slate-800">
                            {{ $vehicle->updated_at->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                        </dd>
                    </div>
                </dl>
            </article>

            @if ($canManage)
                <article class="panel p-5 sm:p-6">
                    <h2 class="panel-title">Status Master</h2>
                    <p class="mt-2 text-xs leading-5 text-slate-500">
                        Menonaktifkan kendaraan tidak menghapus riwayat. Kendaraan yang sedang dipesan atau dipinjam tidak dapat dinonaktifkan.
                    </p>
                    @if ($vehicle->is_active)
                        <form
                            method="POST"
                            action="{{ route('vehicles.deactivate', $vehicle) }}"
                            class="mt-4"
                            data-confirm-message="Nonaktifkan kendaraan ini?"
                        >
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="secondary-button text-red-700">
                                Nonaktifkan Kendaraan
                            </button>
                        </form>
                    @else
                        <form
                            method="POST"
                            action="{{ route('vehicles.activate', $vehicle) }}"
                            class="mt-4"
                        >
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="button-primary-inline w-full">
                                Aktifkan Kembali
                            </button>
                        </form>
                    @endif
                </article>
            @endif
        </aside>
    </section>
</x-layouts.app>

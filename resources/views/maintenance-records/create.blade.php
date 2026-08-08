<x-layouts.app
    title="Tambah Pemeliharaan"
    header="Tambah Pemeliharaan"
    eyebrow="Kendaraan"
>
    @php
        $selectedVehiclePublicId = old(
            'vehicle_public_id',
            $selectedLoan?->vehicle?->public_id,
        );
        $selectedLoanPublicId = old(
            'source_vehicle_loan_public_id',
            $selectedLoan?->public_id,
        );
        $selectedReturnCheck = $selectedLoan?->returnCheck();
    @endphp

    <section class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Pelaporan Pemeliharaan</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Buat Tiket Pemeliharaan
            </h1>
            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-600">
                Gunakan sumber masalah pengembalian bila tersedia agar histori peminjaman dan pemeliharaan tetap terhubung.
            </p>
        </div>
        <a href="{{ route('maintenance-records.index') }}" class="secondary-button sm:w-auto">
            Kembali
        </a>
    </section>

    @if ($errors->any())
        <div class="alert-danger mt-6">
            <strong>Data belum dapat disimpan.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    @if ($returnIssues->isNotEmpty())
        <section class="panel mt-6 p-5 sm:p-6">
            <h2 class="text-lg font-black text-slate-950">Masalah Pengembalian yang Belum Ditindaklanjuti</h2>
            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                @foreach ($returnIssues as $loan)
                    <a
                        href="{{ route('maintenance-records.create-from-loan', $loan) }}"
                        class="rounded-2xl border border-red-200 bg-red-50 p-4 transition hover:border-red-400"
                    >
                        <p class="text-sm font-black text-red-950">{{ $loan->loan_number }}</p>
                        <p class="mt-1 text-xs font-bold text-red-800">
                            {{ $loan->vehicle?->license_plate }} · {{ $loan->vehicle?->displayName() }}
                        </p>
                        <p class="mt-1 text-xs font-semibold text-red-700">
                            Peminjam: {{ $loan->borrower?->name ?: '-' }}
                        </p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($selectedLoan)
        <section class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-5">
            <p class="text-xs font-black uppercase tracking-[.12em] text-red-700">Sumber Masalah Pengembalian</p>
            <p class="mt-2 text-lg font-black text-red-950">{{ $selectedLoan->loan_number }}</p>
            <p class="mt-1 text-sm font-semibold text-red-800">
                {{ $selectedLoan->vehicle_snapshot }} · {{ $selectedLoan->borrower_name_snapshot }}
            </p>
            @if ($selectedReturnCheck?->damage_notes)
                <p class="mt-3 text-sm font-semibold leading-6 text-red-900">
                    {{ $selectedReturnCheck->damage_notes }}
                </p>
            @endif
        </section>
    @endif

    <form
        method="POST"
        action="{{ route('maintenance-records.store') }}"
        enctype="multipart/form-data"
        class="panel mt-6 p-5 sm:p-6"
    >
        @csrf

        <input
            type="hidden"
            name="source_vehicle_loan_public_id"
            value="{{ $selectedLoanPublicId }}"
        >

        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <label for="vehicle_public_id" class="form-label">Kendaraan</label>
                <select
                    id="vehicle_public_id"
                    name="vehicle_public_id"
                    class="form-input"
                    required
                    @disabled($selectedLoan)
                >
                    <option value="">Pilih kendaraan</option>
                    @foreach ($vehicles as $vehicle)
                        <option
                            value="{{ $vehicle->public_id }}"
                            @selected($selectedVehiclePublicId === $vehicle->public_id)
                        >
                            {{ $vehicle->vehicle_code }} · {{ $vehicle->license_plate }} · {{ $vehicle->displayName() }}
                        </option>
                    @endforeach
                </select>
                @if ($selectedLoan)
                    <input type="hidden" name="vehicle_public_id" value="{{ $selectedVehiclePublicId }}">
                @endif
                @error('vehicle_public_id')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="reported_date" class="form-label">Tanggal Laporan</label>
                <input
                    id="reported_date"
                    name="reported_date"
                    type="date"
                    value="{{ old('reported_date', now($displayTimezone)->toDateString()) }}"
                    class="form-input"
                    required
                >
                @error('reported_date')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="maintenance_type" class="form-label">Jenis Pemeliharaan</label>
                <input
                    id="maintenance_type"
                    name="maintenance_type"
                    type="text"
                    value="{{ old('maintenance_type', $selectedLoan ? 'Perbaikan akibat masalah pengembalian' : '') }}"
                    class="form-input"
                    maxlength="100"
                    required
                >
                @error('maintenance_type')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="complaint" class="form-label">Keluhan / Kerusakan</label>
                <textarea
                    id="complaint"
                    name="complaint"
                    rows="4"
                    class="form-input min-h-28 py-3"
                    required
                >{{ old('complaint', $selectedReturnCheck?->damage_notes) }}</textarea>
                @error('complaint')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="initial_condition" class="form-label">Kondisi Awal</label>
                <textarea
                    id="initial_condition"
                    name="initial_condition"
                    rows="4"
                    class="form-input min-h-28 py-3"
                    required
                >{{ old('initial_condition', $selectedReturnCheck ? 'Bodi: '.$selectedReturnCheck->body_condition."\nMesin: ".$selectedReturnCheck->engine_condition."\nBan: ".$selectedReturnCheck->tire_condition."\nKelengkapan: ".$selectedReturnCheck->equipment_condition : '') }}</textarea>
                @error('initial_condition')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="photo_before" class="form-label">Foto Sebelum Pemeliharaan</label>
                <input
                    id="photo_before"
                    name="photo_before"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="form-input py-2"
                    required
                >
                <p class="mt-1 text-xs font-semibold text-slate-500">JPG, PNG, atau WebP. Maksimal 5 MB.</p>
                @error('photo_before')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="supporting_document" class="form-label">Dokumen Pendukung</label>
                <input
                    id="supporting_document"
                    name="supporting_document"
                    type="file"
                    accept="application/pdf,image/jpeg,image/png,image/webp"
                    class="form-input py-2"
                >
                <p class="mt-1 text-xs font-semibold text-slate-500">Opsional. PDF atau gambar, maksimal 5 MB.</p>
                @error('supporting_document')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('maintenance-records.index') }}" class="secondary-button sm:w-auto">Batal</a>
            <button type="submit" class="primary-button sm:w-auto">Simpan Laporan</button>
        </div>
    </form>
</x-layouts.app>

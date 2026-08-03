@php
    $managedVehicle = $vehicle ?? null;
    $statusLocked = $managedVehicle !== null
        && (
            ! $managedVehicle->is_active
            || $managedVehicle->status->isTransactionControlled()
        );
    $odometerLocked = $managedVehicle !== null
        && $managedVehicle->status->isTransactionControlled();
@endphp

<section class="panel">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Identitas Kendaraan</h2>
            <p class="panel-subtitle">Data utama kendaraan dan nomor registrasi internal</p>
        </div>
    </div>

    <div class="grid gap-6 p-5 md:grid-cols-2 sm:p-6">
        <div>
            <label for="vehicle_code" class="form-label">Kode Kendaraan</label>
            <input
                id="vehicle_code"
                name="vehicle_code"
                type="text"
                value="{{ old('vehicle_code', $managedVehicle?->vehicle_code) }}"
                class="form-input @error('vehicle_code') form-input-error @enderror"
                maxlength="80"
                placeholder="Contoh: KND-001"
                required
                autofocus
            >
            @error('vehicle_code')
                <p class="form-error">{{ $message }}</p>
            @enderror
            <p class="mt-2 text-xs font-medium text-slate-500">
                Gunakan huruf, angka, titik, garis miring, atau tanda hubung.
            </p>
        </div>

        <div>
            <label for="license_plate" class="form-label">Nomor Polisi</label>
            <input
                id="license_plate"
                name="license_plate"
                type="text"
                value="{{ old('license_plate', $managedVehicle?->license_plate) }}"
                class="form-input @error('license_plate') form-input-error @enderror"
                maxlength="30"
                placeholder="Contoh: S 1234 WI"
                required
            >
            @error('license_plate')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="brand" class="form-label">Merek</label>
            <input
                id="brand"
                name="brand"
                type="text"
                value="{{ old('brand', $managedVehicle?->brand) }}"
                class="form-input @error('brand') form-input-error @enderror"
                maxlength="255"
                placeholder="Contoh: Honda"
                required
            >
            @error('brand')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="model" class="form-label">Tipe / Model</label>
            <input
                id="model"
                name="model"
                type="text"
                value="{{ old('model', $managedVehicle?->model) }}"
                class="form-input @error('model') form-input-error @enderror"
                maxlength="255"
                placeholder="Contoh: Vario 160 CBS"
                required
            >
            @error('model')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="year" class="form-label">
                Tahun
                <span class="font-medium text-slate-500">(opsional)</span>
            </label>
            <input
                id="year"
                name="year"
                type="number"
                value="{{ old('year', $managedVehicle?->year) }}"
                class="form-input @error('year') form-input-error @enderror"
                min="1900"
                max="{{ now()->timezone(config('simantap.display_timezone', 'Asia/Jakarta'))->year + 1 }}"
                inputmode="numeric"
                placeholder="Contoh: 2025"
            >
            @error('year')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="color" class="form-label">
                Warna
                <span class="font-medium text-slate-500">(opsional)</span>
            </label>
            <input
                id="color"
                name="color"
                type="text"
                value="{{ old('color', $managedVehicle?->color) }}"
                class="form-input @error('color') form-input-error @enderror"
                maxlength="80"
                placeholder="Contoh: Hitam"
            >
            @error('color')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Dokumen dan Identitas Teknis</h2>
            <p class="panel-subtitle">Nomor rangka, nomor mesin, dan masa berlaku registrasi</p>
        </div>
    </div>

    <div class="grid gap-6 p-5 md:grid-cols-2 sm:p-6">
        <div>
            <label for="chassis_number" class="form-label">
                Nomor Rangka
                <span class="font-medium text-slate-500">(opsional)</span>
            </label>
            <input
                id="chassis_number"
                name="chassis_number"
                type="text"
                value="{{ old('chassis_number', $managedVehicle?->chassis_number) }}"
                class="form-input @error('chassis_number') form-input-error @enderror"
                maxlength="120"
                autocomplete="off"
            >
            @error('chassis_number')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="engine_number" class="form-label">
                Nomor Mesin
                <span class="font-medium text-slate-500">(opsional)</span>
            </label>
            <input
                id="engine_number"
                name="engine_number"
                type="text"
                value="{{ old('engine_number', $managedVehicle?->engine_number) }}"
                class="form-input @error('engine_number') form-input-error @enderror"
                maxlength="120"
                autocomplete="off"
            >
            @error('engine_number')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="registration_expiry_date" class="form-label">
                Masa Berlaku STNK
                <span class="font-medium text-slate-500">(opsional)</span>
            </label>
            <input
                id="registration_expiry_date"
                name="registration_expiry_date"
                type="date"
                value="{{ old('registration_expiry_date', $managedVehicle?->registration_expiry_date?->toDateString()) }}"
                class="form-input @error('registration_expiry_date') form-input-error @enderror"
            >
            @error('registration_expiry_date')
                <p class="form-error">{{ $message }}</p>
            @enderror
            <p class="mt-2 text-xs font-medium text-slate-500">
                Sistem menandai dokumen yang kedaluwarsa atau berakhir dalam 30 hari.
            </p>
        </div>

        <div>
            <label for="responsible_person" class="form-label">
                Penanggung Jawab
                <span class="font-medium text-slate-500">(opsional)</span>
            </label>
            <input
                id="responsible_person"
                name="responsible_person"
                type="text"
                value="{{ old('responsible_person', $managedVehicle?->responsible_person) }}"
                class="form-input @error('responsible_person') form-input-error @enderror"
                maxlength="255"
                placeholder="Nama pengelola atau penanggung jawab"
            >
            @error('responsible_person')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Kondisi Operasional</h2>
            <p class="panel-subtitle">Odometer, status, lokasi, dan foto kendaraan</p>
        </div>
    </div>

    <div class="grid gap-6 p-5 md:grid-cols-2 sm:p-6">
        <div>
            <label for="current_odometer" class="form-label">Odometer Saat Ini</label>
            @if ($odometerLocked)
                <input
                    type="hidden"
                    name="current_odometer"
                    value="{{ $managedVehicle->current_odometer }}"
                >
                <div class="flex min-h-13 items-center rounded-2xl border border-slate-200 bg-slate-100 px-4 text-sm font-black text-slate-700">
                    {{ number_format((float) $managedVehicle->current_odometer, 1, ',', '.') }} km
                </div>
            @else
                <div class="relative">
                    <input
                        id="current_odometer"
                        name="current_odometer"
                        type="number"
                        value="{{ old('current_odometer', $managedVehicle?->current_odometer ?? '0') }}"
                        class="form-input pr-16 @error('current_odometer') form-input-error @enderror"
                        min="{{ $managedVehicle?->current_odometer ?? 0 }}"
                        max="99999999999.9"
                        step="0.1"
                        inputmode="decimal"
                        required
                    >
                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-black text-slate-500">
                        KM
                    </span>
                </div>
            @endif
            @error('current_odometer')
                <p class="form-error">{{ $message }}</p>
            @enderror
            @if ($odometerLocked)
                <p class="mt-2 text-xs font-medium leading-5 text-slate-500">
                    Odometer dikendalikan oleh pemeriksaan serah terima selama kendaraan dipesan atau dipinjam.
                </p>
            @elseif ($managedVehicle !== null)
                <p class="mt-2 text-xs font-medium text-slate-500">
                    Odometer tidak boleh lebih kecil dari catatan saat ini.
                </p>
            @endif
        </div>

        <div>
            <label for="status" class="form-label">Status Operasional</label>
            @if ($statusLocked)
                <input type="hidden" name="status" value="{{ $managedVehicle->status->value }}">
                <div class="flex min-h-13 items-center rounded-2xl border border-slate-200 bg-slate-100 px-4 text-sm font-black text-slate-700">
                    {{ $managedVehicle->status->label() }}
                </div>
                <p class="mt-2 text-xs font-medium leading-5 text-slate-500">
                    {{ ! $managedVehicle->is_active
                        ? 'Aktifkan kendaraan terlebih dahulu untuk mengubah status operasional.'
                        : 'Status ini dikendalikan oleh alur peminjaman dan tidak dapat diubah dari master kendaraan.' }}
                </p>
            @else
                <select
                    id="status"
                    name="status"
                    class="form-input @error('status') form-input-error @enderror"
                    required
                >
                    @foreach ($statusOptions as $statusOption)
                        <option
                            value="{{ $statusOption->value }}"
                            @selected(old('status', $managedVehicle?->status->value ?? \App\Enums\VehicleStatus::Available->value) === $statusOption->value)
                        >
                            {{ $statusOption->label() }}
                        </option>
                    @endforeach
                </select>
            @endif
            @error('status')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="storage_location" class="form-label">
                Lokasi Penyimpanan
                <span class="font-medium text-slate-500">(opsional)</span>
            </label>
            <input
                id="storage_location"
                name="storage_location"
                type="text"
                value="{{ old('storage_location', $managedVehicle?->storage_location) }}"
                class="form-input @error('storage_location') form-input-error @enderror"
                maxlength="255"
                placeholder="Contoh: Garasi Kantor, Slot A-01"
            >
            @error('storage_location')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="image" class="form-label">
                Foto Kendaraan
                <span class="font-medium text-slate-500">(opsional)</span>
            </label>
            <input
                id="image"
                name="image"
                type="file"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                class="form-input py-3 @error('image') form-input-error @enderror"
            >
            @error('image')
                <p class="form-error">{{ $message }}</p>
            @enderror
            <p class="mt-2 text-xs font-medium text-slate-500">
                JPG, PNG, atau WebP. Maksimal 3 MB.
            </p>

            @if ($managedVehicle?->image_path)
                <div class="mt-4 flex items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                    <img
                        src="{{ asset('storage/'.$managedVehicle->image_path) }}"
                        alt="Foto kendaraan saat ini"
                        class="h-16 w-20 rounded-xl object-cover ring-1 ring-slate-200"
                    >
                    <label class="flex items-center gap-3 text-xs font-bold text-slate-600">
                        <input
                            name="remove_image"
                            type="checkbox"
                            value="1"
                            class="h-4 w-4 rounded border-slate-300 text-sky-600"
                            @checked(old('remove_image'))
                        >
                        Hapus foto lama
                    </label>
                </div>
            @elseif ($managedVehicle !== null)
                <input name="remove_image" type="hidden" value="0">
            @endif
        </div>

        <div class="md:col-span-2">
            <label for="notes" class="form-label">
                Catatan Kendaraan
                <span class="font-medium text-slate-500">(opsional)</span>
            </label>
            <textarea
                id="notes"
                name="notes"
                rows="4"
                class="form-input py-4 @error('notes') form-input-error @enderror"
                maxlength="3000"
                placeholder="Keterangan tambahan yang relevan dengan kendaraan"
            >{{ old('notes', $managedVehicle?->notes) }}</textarea>
            @error('notes')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        @if ($managedVehicle === null)
            <div class="md:col-span-2">
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <input
                        name="is_active"
                        type="checkbox"
                        value="1"
                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-sky-600"
                        @checked(old('is_active', true))
                    >
                    <span>
                        <span class="block text-sm font-extrabold text-slate-800">
                            Kendaraan aktif dan dapat digunakan
                        </span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">
                            Kendaraan nonaktif tidak dapat dipilih pada pengajuan peminjaman baru.
                        </span>
                    </span>
                </label>
            </div>
        @endif
    </div>
</section>

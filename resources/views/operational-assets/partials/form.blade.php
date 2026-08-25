@php
    $managedAsset = $asset ?? null;
    $statusLocked = $managedAsset !== null
        && in_array($managedAsset->status, [
            \App\Enums\OperationalAssetStatus::Maintenance,
            \App\Enums\OperationalAssetStatus::Inactive,
        ], true);
@endphp

<section class="panel">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Identitas Aset Perangkat</h2>
            <p class="panel-subtitle">Register individual PC, laptop, atau printer</p>
        </div>
    </div>

    <div class="grid gap-6 p-5 md:grid-cols-2 sm:p-6">
        <div>
            <label for="asset_code" class="form-label">Kode Aset</label>
            <input id="asset_code" name="asset_code" type="text" value="{{ old('asset_code', $managedAsset?->asset_code) }}" class="form-input @error('asset_code') form-input-error @enderror" maxlength="80" placeholder="Contoh: 3100102001-35" required autofocus>
            @error('asset_code')<p class="form-error">{{ $message }}</p>@enderror
            <p class="mt-2 text-xs font-medium text-slate-500">Gunakan kode internal yang unik. Format Kode Barang-NUP dapat dipakai untuk data BMN.</p>
        </div>

        <div>
            <label for="type" class="form-label">Jenis Perangkat</label>
            <select id="type" name="type" class="form-input @error('type') form-input-error @enderror" required>
                @foreach ($typeOptions as $typeOption)
                    <option value="{{ $typeOption->value }}" @selected(old('type', $managedAsset?->type->value ?? \App\Enums\OperationalAssetType::Pc->value) === $typeOption->value)>
                        {{ $typeOption->label() }}
                    </option>
                @endforeach
            </select>
            @error('type')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="brand" class="form-label">Merek</label>
            <input id="brand" name="brand" type="text" value="{{ old('brand', $managedAsset?->brand) }}" class="form-input @error('brand') form-input-error @enderror" maxlength="255" placeholder="Contoh: Lenovo" required>
            @error('brand')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="model" class="form-label">Tipe / Model <span class="font-medium text-slate-500">(opsional)</span></label>
            <input id="model" name="model" type="text" value="{{ old('model', $managedAsset?->model) }}" class="form-input @error('model') form-input-error @enderror" maxlength="255" placeholder="Contoh: ThinkCentre M720T">
            @error('model')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="serial_number" class="form-label">Nomor Seri <span class="font-medium text-slate-500">(opsional)</span></label>
            <input id="serial_number" name="serial_number" type="text" value="{{ old('serial_number', $managedAsset?->serial_number) }}" class="form-input @error('serial_number') form-input-error @enderror" maxlength="120" autocomplete="off">
            @error('serial_number')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="acquisition_year" class="form-label">Tahun Perolehan <span class="font-medium text-slate-500">(opsional)</span></label>
            <input id="acquisition_year" name="acquisition_year" type="number" value="{{ old('acquisition_year', $managedAsset?->acquisition_year) }}" class="form-input @error('acquisition_year') form-input-error @enderror" min="1900" max="{{ now()->timezone(config('simantap.display_timezone', 'Asia/Jakarta'))->year + 1 }}" inputmode="numeric">
            @error('acquisition_year')<p class="form-error">{{ $message }}</p>@enderror
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Referensi BMN</h2>
            <p class="panel-subtitle">Pemetaan mengikuti kolom utama pada daftar aset BPS</p>
        </div>
    </div>

    <div class="grid gap-6 p-5 md:grid-cols-2 sm:p-6">
        <div>
            <label for="bmn_code" class="form-label">Kode Barang BMN <span class="font-medium text-slate-500">(opsional)</span></label>
            <input id="bmn_code" name="bmn_code" type="text" value="{{ old('bmn_code', $managedAsset?->bmn_code) }}" class="form-input @error('bmn_code') form-input-error @enderror" maxlength="50" placeholder="Contoh: 3100102001">
            @error('bmn_code')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="nup" class="form-label">NUP <span class="font-medium text-slate-500">(opsional)</span></label>
            <input id="nup" name="nup" type="text" value="{{ old('nup', $managedAsset?->nup) }}" class="form-input @error('nup') form-input-error @enderror" maxlength="30" inputmode="numeric" placeholder="Contoh: 35">
            @error('nup')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div class="md:col-span-2">
            <label for="register_code" class="form-label">Kode Register <span class="font-medium text-slate-500">(opsional)</span></label>
            <input id="register_code" name="register_code" type="text" value="{{ old('register_code', $managedAsset?->register_code) }}" class="form-input @error('register_code') form-input-error @enderror" maxlength="100" autocomplete="off">
            @error('register_code')<p class="form-error">{{ $message }}</p>@enderror
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Penempatan dan Status</h2>
            <p class="panel-subtitle">Lokasi, pengelola, kondisi operasional, dan catatan</p>
        </div>
    </div>

    <div class="grid gap-6 p-5 md:grid-cols-2 sm:p-6">
        <div>
            <label for="location" class="form-label">Lokasi Ruang <span class="font-medium text-slate-500">(opsional)</span></label>
            <input id="location" name="location" type="text" value="{{ old('location', $managedAsset?->location) }}" class="form-input @error('location') form-input-error @enderror" maxlength="255" placeholder="Contoh: 00004 - Ruang Staf">
            @error('location')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="responsible_person" class="form-label">Penanggung Jawab <span class="font-medium text-slate-500">(opsional)</span></label>
            <input id="responsible_person" name="responsible_person" type="text" value="{{ old('responsible_person', $managedAsset?->responsible_person) }}" class="form-input @error('responsible_person') form-input-error @enderror" maxlength="255">
            @error('responsible_person')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="status" class="form-label">Status Operasional</label>
            @if ($statusLocked)
                <input type="hidden" name="status" value="{{ $managedAsset->status->value }}">
                <div class="flex min-h-13 items-center rounded-2xl border border-slate-200 bg-slate-100 px-4 text-sm font-black text-slate-700">{{ $managedAsset->status->label() }}</div>
                <p class="mt-2 text-xs font-medium leading-5 text-slate-500">Status ini dikendalikan oleh alur pemeliharaan atau aktivasi master.</p>
            @else
                <select id="status" name="status" class="form-input @error('status') form-input-error @enderror" required>
                    @foreach ($statusOptions as $statusOption)
                        <option value="{{ $statusOption->value }}" @selected(old('status', $managedAsset?->status->value ?? \App\Enums\OperationalAssetStatus::Available->value) === $statusOption->value)>{{ $statusOption->label() }}</option>
                    @endforeach
                </select>
            @endif
            @error('status')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        @if ($managedAsset === null)
            <div>
                <label for="is_active" class="form-label">Status Master</label>
                <input type="hidden" name="is_active" value="0">
                <label class="flex min-h-13 items-center gap-3 rounded-2xl border border-slate-200 px-4 text-sm font-bold text-slate-700">
                    <input id="is_active" name="is_active" type="checkbox" value="1" class="h-4 w-4 rounded border-slate-300 text-sky-600" @checked(old('is_active', true))>
                    Aktif dan dapat digunakan
                </label>
                @error('is_active')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        @endif

        <div class="md:col-span-2">
            <label for="notes" class="form-label">Catatan Aset <span class="font-medium text-slate-500">(opsional)</span></label>
            <textarea id="notes" name="notes" rows="4" class="form-input min-h-28 py-3 @error('notes') form-input-error @enderror">{{ old('notes', $managedAsset?->notes) }}</textarea>
            @error('notes')<p class="form-error">{{ $message }}</p>@enderror
        </div>
    </div>
</section>

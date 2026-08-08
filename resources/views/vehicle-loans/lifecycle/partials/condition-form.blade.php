@php
    $formKey = $formKey ?? 'condition';
    $buttonLabel = $buttonLabel ?? 'Simpan Pemeriksaan';
    $heading = $heading ?? 'Pemeriksaan Kondisi Kendaraan';
    $description = $description ?? 'Lengkapi kondisi kendaraan dan seluruh foto wajib.';
@endphp

<form
    method="POST"
    action="{{ $action }}"
    enctype="multipart/form-data"
    class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5"
>
    @csrf

    <div>
        <p class="text-sm font-black text-slate-950">{{ $heading }}</p>
        <p class="mt-1 text-xs font-semibold leading-5 text-slate-600">
            {{ $description }}
        </p>
    </div>

    <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <div>
            <label for="{{ $formKey }}_odometer" class="form-label">Odometer</label>
            <input
                id="{{ $formKey }}_odometer"
                name="odometer"
                type="number"
                min="0"
                step="0.1"
                value="{{ old('odometer') }}"
                class="form-input"
                required
            >
            @error('odometer')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $formKey }}_fuel_level" class="form-label">
                Bahan Bakar (0 sampai 100 persen)
            </label>
            <input
                id="{{ $formKey }}_fuel_level"
                name="fuel_level"
                type="number"
                min="0"
                max="100"
                step="1"
                value="{{ old('fuel_level') }}"
                class="form-input"
                required
            >
            @error('fuel_level')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-2">
            <label for="{{ $formKey }}_overall_condition" class="form-label">
                Kondisi Keseluruhan
            </label>
            <select
                id="{{ $formKey }}_overall_condition"
                name="overall_condition"
                class="form-input"
                required
            >
                <option value="">Pilih kondisi</option>
                @foreach (\App\Enums\VehicleOverallCondition::cases() as $condition)
                    <option value="{{ $condition->value }}" @selected(old('overall_condition') === $condition->value)>
                        {{ $condition->label() }}
                    </option>
                @endforeach
            </select>
            @error('overall_condition')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        @foreach ([
            'body_condition' => 'Kondisi Bodi',
            'engine_condition' => 'Kondisi Mesin',
            'tire_condition' => 'Kondisi Ban',
            'equipment_condition' => 'Kondisi Kelengkapan',
        ] as $field => $label)
            <div>
                <label for="{{ $formKey }}_{{ $field }}" class="form-label">
                    {{ $label }}
                </label>
                <textarea
                    id="{{ $formKey }}_{{ $field }}"
                    name="{{ $field }}"
                    rows="3"
                    class="form-input min-h-24 py-3"
                    required
                >{{ old($field) }}</textarea>
                @error($field)
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        <div class="sm:col-span-2">
            <label for="{{ $formKey }}_damage_notes" class="form-label">
                Catatan Kerusakan atau Perhatian
            </label>
            <textarea
                id="{{ $formKey }}_damage_notes"
                name="damage_notes"
                rows="3"
                class="form-input min-h-24 py-3"
                placeholder="Wajib bila kondisi keseluruhan bukan Baik"
            >{{ old('damage_notes') }}</textarea>
            @error('damage_notes')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="mt-5 rounded-2xl border border-sky-200 bg-white p-4">
        <p class="text-xs font-black uppercase tracking-[.12em] text-sky-800">
            Foto Wajib
        </p>
        <p class="mt-1 text-xs font-semibold leading-5 text-slate-600">
            Format JPG, JPEG, PNG, atau WEBP. Ukuran mengikuti batas evidence pada konfigurasi SIMANTAP.
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @foreach ([
                'photo_front' => 'Tampak Depan',
                'photo_back' => 'Tampak Belakang',
                'photo_left' => 'Sisi Kiri',
                'photo_right' => 'Sisi Kanan',
                'photo_odometer' => 'Odometer',
                'photo_fuel' => 'Indikator Bahan Bakar',
            ] as $field => $label)
                <div>
                    <label for="{{ $formKey }}_{{ $field }}" class="form-label">
                        {{ $label }}
                    </label>
                    <input
                        id="{{ $formKey }}_{{ $field }}"
                        name="{{ $field }}"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="form-input py-2"
                        required
                    >
                    @error($field)
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="sm:col-span-2">
                <label for="{{ $formKey }}_photo_damage" class="form-label">
                    Foto Kerusakan atau Temuan
                </label>
                <input
                    id="{{ $formKey }}_photo_damage"
                    name="photo_damage"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="form-input py-2"
                >
                <p class="mt-1 text-xs font-semibold text-slate-500">
                    Wajib bila kondisi keseluruhan bukan Baik.
                </p>
                @error('photo_damage')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <button type="submit" class="primary-button mt-5">
        {{ $buttonLabel }}
    </button>
</form>

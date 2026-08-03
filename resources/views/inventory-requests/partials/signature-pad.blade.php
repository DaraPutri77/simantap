@php
    $padId = $padId ?? 'signature';
    $consentName = $consentName ?? 'signature_consent';
    $consentText = $consentText
        ?? 'Saya menyatakan tanda tangan ini benar dibubuhkan oleh saya.';
@endphp

<div class="signature-pad" data-signature-pad>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="font-black text-slate-950">Tanda Tangan Digital</p>
            <p class="mt-1 text-xs font-semibold text-slate-600">
                Gunakan mouse, touchpad, atau sentuhan pada layar HP.
            </p>
        </div>
        <button
            type="button"
            class="secondary-button sm:w-auto"
            data-signature-clear
        >
            Hapus Tanda Tangan
        </button>
    </div>

    <div class="mt-4 overflow-hidden rounded-2xl border-2 border-slate-400 bg-white">
        <canvas
            id="{{ $padId }}_canvas"
            class="block h-48 w-full touch-none cursor-crosshair"
            data-signature-canvas
            aria-label="Area tanda tangan digital"
            tabindex="0"
        ></canvas>
    </div>

    <input
        id="{{ $padId }}_data"
        name="signature_data"
        type="hidden"
        value="{{ old('signature_data') }}"
        data-signature-input
    >
    <p class="mt-2 hidden text-xs font-bold text-red-700" data-signature-error>
        Bubuhkan tanda tangan sebelum melanjutkan.
    </p>
    @error('signature_data')
        <p class="form-error">{{ $message }}</p>
    @enderror

    <label class="mt-4 flex items-start gap-3 rounded-2xl border border-slate-300 bg-slate-50 p-4">
        <input
            name="{{ $consentName }}"
            type="checkbox"
            value="1"
            class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-400 text-sky-700 focus:ring-sky-300"
            @checked(old($consentName))
            required
        >
        <span class="text-xs font-semibold leading-5 text-slate-700">
            {{ $consentText }}
        </span>
    </label>
    @if ($errors->has($consentName))
        <p class="form-error">{{ $errors->first($consentName) }}</p>
    @endif
</div>

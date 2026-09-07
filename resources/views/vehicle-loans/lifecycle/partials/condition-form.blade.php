@php
    $formKey = $formKey ?? 'condition';
    $buttonLabel = $buttonLabel ?? 'Simpan Pengembalian';
    $heading = $heading ?? 'Bukti Pengembalian Kendaraan';
    $description = $description ?? 'Konfirmasi kelengkapan kunci dan STNK, lalu unggah foto serah terima.';
    $signaturePadId = $signaturePadId ?? $formKey.'_officer';
    $signatureConsentText = $signatureConsentText
        ?? 'Saya menyatakan telah menerima pengembalian kendaraan ini beserta STNK dan kuncinya.';
@endphp

<form
    method="POST"
    action="{{ $action }}"
    enctype="multipart/form-data"
    class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5"
    data-signature-form
>
    @csrf

    <div>
        <p class="text-sm font-black text-slate-950">{{ $heading }}</p>
        <p class="mt-1 text-xs font-semibold leading-5 text-slate-600">
            {{ $description }}
        </p>
    </div>

    <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <!-- Checklist Kelengkapan -->
        <div class="sm:col-span-2 rounded-2xl border border-sky-200 bg-white p-4">
            <p class="text-xs font-black uppercase tracking-[.12em] text-sky-800 mb-3">
                Kelengkapan Pengembalian
            </p>
            
            <div class="space-y-3">
                <label class="flex items-center gap-3">
                    <input 
                        type="checkbox" 
                        name="key_returned" 
                        class="h-5 w-5 rounded border-slate-300 text-sky-600 focus:ring-sky-600"
                        required
                    >
                    <span class="text-sm font-semibold text-slate-700">Kunci Kendaraan Dikembalikan</span>
                </label>
                
                <label class="flex items-center gap-3">
                    <input 
                        type="checkbox" 
                        name="stnk_returned" 
                        class="h-5 w-5 rounded border-slate-300 text-sky-600 focus:ring-sky-600"
                        required
                    >
                    <span class="text-sm font-semibold text-slate-700">STNK Kendaraan Dikembalikan</span>
                </label>
            </div>
            <p class="mt-3 text-[11px] font-medium leading-5 text-slate-500">
                Centang kedua kotak di atas untuk memastikan kelengkapan operasional.
            </p>
        </div>

        <!-- Foto Bukti Tunggal -->
        <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-white p-4">
            <label for="{{ $formKey }}_photo_proof" class="form-label">
                Foto Bukti Pengembalian
            </label>
            <input
                id="{{ $formKey }}_photo_proof"
                name="photo_proof"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                capture="environment"
                class="form-input py-2 mt-1"
                data-evidence-preview-input
                required
            >
            <div class="mt-2 hidden rounded-xl border border-slate-200 bg-slate-50 p-2" data-evidence-preview>
                <img
                    src=""
                    alt="Pratinjau Foto Bukti Pengembalian"
                    class="h-40 w-full rounded-lg object-contain"
                    data-evidence-preview-image
                >
                <p class="mt-2 truncate text-[10px] font-bold text-slate-600" data-evidence-preview-name></p>
            </div>
            <p class="mt-1 text-xs font-semibold text-slate-500">
                Unggah 1 foto bukti serah terima (kendaraan/kunci/STNK).
            </p>
            @error('photo_proof')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <!-- Tanda Tangan -->
    <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
        @include('inventory-requests.partials.signature-pad', [
            'padId' => $signaturePadId,
            'consentName' => 'condition_consent',
            'consentText' => $signatureConsentText,
        ])
    </div>

    <button type="submit" class="primary-button mt-5 w-full sm:w-auto">
        {{ $buttonLabel }}
    </button>
</form>
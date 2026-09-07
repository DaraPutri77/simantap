<?php

namespace App\Http\Requests;

use App\Rules\SignatureDataUrl;
use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleConditionCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $imageRules = [
            'required',
            'file',
            'image',
            'mimetypes:image/jpeg,image/png,image/webp',
            'extensions:jpg,jpeg,png,webp',
            'max:'.(int) config('simantap.uploads.evidence_max_size_kb', 5120),
        ];

        return [
            'key_returned' => ['accepted'],
            'stnk_returned' => ['accepted'],
            'photo_proof' => $imageRules,
            'signature_data' => ['required', new SignatureDataUrl],
            'condition_consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key_returned.accepted' => 'Konfirmasi pengembalian kunci wajib dicentang.',
            'stnk_returned.accepted' => 'Konfirmasi pengembalian STNK wajib dicentang.',
            'photo_proof.required' => 'Foto bukti pengembalian wajib diunggah.',
            'signature_data.required' => 'Tanda tangan petugas wajib dibubuhkan.',
            'condition_consent.accepted' => 'Pernyataan pertanggungjawaban pemeriksaan wajib disetujui.',
        ];
    }
}
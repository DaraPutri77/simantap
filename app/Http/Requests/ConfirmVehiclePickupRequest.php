<?php

namespace App\Http\Requests;

use App\Rules\SignatureDataUrl;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmVehiclePickupRequest extends FormRequest
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
        return [
            'photo_borrower_with_key' => [
                'required',
                'file',
                'image',
                'mimetypes:image/jpeg,image/png,image/webp',
                'extensions:jpg,jpeg,png,webp',
                'max:'.(int) config(
                    'simantap.uploads.evidence_max_size_kb',
                    5120,
                ),
            ],
            'signature_data' => ['required', new SignatureDataUrl],
            'pickup_consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo_borrower_with_key.required' => 'Foto peminjam memegang kunci kendaraan wajib diunggah.',
        ];
    }
}

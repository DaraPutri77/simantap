<?php

namespace App\Http\Requests;

use App\Rules\SignatureDataUrl;
use Illuminate\Foundation\Http\FormRequest;

class RequestVehicleReturnRequest extends FormRequest
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
            'return_confirmation' => ['accepted'],
            'return_notes' => ['nullable', 'string', 'max:4000'],
            'signature_data' => ['required', new SignatureDataUrl],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'return_confirmation.accepted' => 'Pernyataan pengembalian wajib disetujui.',
            'signature_data.required' => 'Tanda tangan peminjam saat pengembalian wajib dibubuhkan.',
        ];
    }
}

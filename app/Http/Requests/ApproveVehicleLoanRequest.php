<?php

namespace App\Http\Requests;

use App\Rules\SignatureDataUrl;
use Illuminate\Foundation\Http\FormRequest;

class ApproveVehicleLoanRequest extends FormRequest
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
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'signature_data' => ['required', new SignatureDataUrl],
            'approval_consent' => ['accepted'],
        ];
    }
}

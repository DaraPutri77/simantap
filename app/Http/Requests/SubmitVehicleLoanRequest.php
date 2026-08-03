<?php

namespace App\Http\Requests;

use App\Rules\SignatureDataUrl;
use Illuminate\Foundation\Http\FormRequest;

class SubmitVehicleLoanRequest extends FormRequest
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
            'signature_data' => ['required', new SignatureDataUrl],
            'signature_consent' => ['accepted'],
        ];
    }
}

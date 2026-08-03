<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectInventoryRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rejection_reason' => trim(
                (string) $this->input('rejection_reason'),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => [
                'required',
                'string',
                'min:5',
                'max:3000',
            ],
        ];
    }
}

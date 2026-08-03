<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AwaitInventoryRequestStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'admin_notes' => trim(
                (string) $this->input('admin_notes'),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admin_notes' => [
                'required',
                'string',
                'min:5',
                'max:3000',
            ],
        ];
    }
}

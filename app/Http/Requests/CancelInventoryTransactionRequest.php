<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class CancelInventoryTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName::StockManage->value,
        ) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cancellation_reason' => trim(
                (string) $this->input('cancellation_reason'),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => [
                'required',
                'string',
                'min:5',
                'max:2000',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'cancellation_reason' => 'alasan pembatalan',
        ];
    }
}

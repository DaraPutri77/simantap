<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
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
            'reason' => trim((string) $this->input('reason')),
            'notes' => $this->nullableText('notes'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'adjustment_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('items', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'items.*.physical_quantity' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999999.99',
                'decimal:0,2',
            ],
            'items.*.notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'adjustment_date' => 'tanggal penyesuaian',
            'reason' => 'alasan penyesuaian',
            'items' => 'daftar barang',
            'items.*.item_id' => 'barang',
            'items.*.physical_quantity' => 'jumlah fisik',
            'items.*.notes' => 'catatan barang',
        ];
    }

    private function nullableText(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}

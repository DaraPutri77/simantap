<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryReceiptRequest extends FormRequest
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
            'source' => trim((string) $this->input('source')),
            'reference_number' => $this->nullableText(
                'reference_number',
            ),
            'notes' => $this->nullableText('notes'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'receipt_date' => ['required', 'date'],
            'source' => ['required', 'string', 'max:255'],
            'reference_number' => [
                'nullable',
                'string',
                'max:255',
            ],
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
            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:9999999999999.99',
                'decimal:0,2',
            ],
            'items.*.unit_cost' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999999999999.99',
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
            'receipt_date' => 'tanggal penerimaan',
            'source' => 'sumber barang',
            'reference_number' => 'nomor referensi',
            'items' => 'daftar barang',
            'items.*.item_id' => 'barang',
            'items.*.quantity' => 'jumlah barang',
            'items.*.unit_cost' => 'harga satuan',
            'items.*.notes' => 'catatan barang',
        ];
    }

    private function nullableText(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}

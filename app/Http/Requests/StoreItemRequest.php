<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName::ItemManage->value,
        ) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'item_code' => mb_strtoupper(
                trim((string) $this->input('item_code')),
            ),
            'name' => trim((string) $this->input('name')),
            'description' => $this->nullableText('description'),
            'storage_location' => $this->nullableText(
                'storage_location',
            ),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'item_code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Z0-9._\/-]+$/',
                Rule::unique('items', 'item_code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('item_categories', 'id')
                    ->whereNull('deleted_at'),
            ],
            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:3000'],
            'initial_stock' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999999.99',
                'decimal:0,2',
            ],
            'minimum_stock' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999999.99',
                'decimal:0,2',
            ],
            'storage_location' => [
                'nullable',
                'string',
                'max:255',
            ],
            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'item_code' => 'kode barang',
            'name' => 'nama barang',
            'category_id' => 'kategori',
            'unit_id' => 'satuan',
            'initial_stock' => 'stok awal',
            'minimum_stock' => 'stok minimum',
            'storage_location' => 'lokasi penyimpanan',
            'image' => 'foto barang',
            'is_active' => 'status barang',
        ];
    }

    private function nullableText(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}

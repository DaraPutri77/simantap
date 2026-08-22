<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Models\InventoryReceipt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $receiptNumber = $this->nullableText('receipt_number');

        $this->merge([
            'receipt_number' => $receiptNumber === null
                ? null
                : mb_strtoupper($receiptNumber),
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
        $receipt = $this->route('inventory_receipt');

        return [
            'receipt_number' => [
                'nullable',
                'string',
                'max:80',
                'regex:/^[A-Z0-9._\\/-]+$/',
                Rule::unique('inventory_receipts', 'receipt_number')
                    ->ignore(
                        $receipt instanceof InventoryReceipt
                            ? $receipt->getKey()
                            : null,
                    ),
            ],

            'receipt_date' => [
                'required',
                'date',
            ],

            'source' => [
                'required',
                'string',
                'max:255',
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],

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

            'items.*.notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $receipt = $this->route('inventory_receipt');
            $receiptNumber = (string) ($this->input('receipt_number') ?? '');
            $usesAutomaticNamespace = str_starts_with(
                $receiptNumber,
                'STK-IN/',
            );
            $keepsExistingAutomaticNumber = $receipt instanceof InventoryReceipt
                && $receipt->receipt_number === $receiptNumber;

            if (
                $usesAutomaticNamespace
                && ! $keepsExistingAutomaticNumber
            ) {
                $validator->errors()->add(
                    'receipt_number',
                    'Awalan STK-IN/ disediakan untuk nomor otomatis SIMANTAP. Gunakan nomor manual lain, misalnya BAST/001/2026, atau kosongkan field agar nomor dibuat otomatis.',
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'receipt_number' => 'nomor barang masuk',
            'receipt_date' => 'tanggal penerimaan',
            'source' => 'sumber barang',
            'reference_number' => 'nomor referensi',
            'items' => 'daftar barang',
            'items.*.item_id' => 'barang',
            'items.*.quantity' => 'jumlah barang',
            'items.*.notes' => 'catatan barang',
        ];
    }

    public function messages(): array
    {
        return [
            'receipt_number.regex' => 'Nomor barang masuk hanya boleh berisi huruf, angka, titik, garis bawah, garis miring, dan tanda hubung.',
            'receipt_number.unique' => 'Nomor barang masuk sudah digunakan oleh dokumen lain.',
        ];
    }

    private function nullableText(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}

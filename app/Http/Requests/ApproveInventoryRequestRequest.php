<?php

namespace App\Http\Requests;

use App\Models\InventoryRequest;
use App\Rules\SignatureDataUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApproveInventoryRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'admin_notes' => $this->filled('admin_notes')
                ? trim((string) $this->input('admin_notes'))
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*' => ['required', 'array'],
            'items.*.approved_quantity' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999999.99',
            ],
            'items.*.admin_notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'admin_notes' => ['nullable', 'string', 'max:3000'],
            'signature_data' => [
                'required',
                new SignatureDataUrl,
            ],
            'signature_consent' => ['accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $inventoryRequest = $this->route('inventory_request');

            if (! $inventoryRequest instanceof InventoryRequest) {
                return;
            }

            $requestItems = $inventoryRequest->items()
                ->get(['id', 'requested_quantity'])
                ->keyBy('id');
            $submittedItems = collect($this->input('items', []));

            if (
                $submittedItems->keys()
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->sort()
                    ->values()
                    ->all()
                !== $requestItems->keys()->sort()->values()->all()
            ) {
                $validator->errors()->add(
                    'items',
                    'Daftar barang yang diperiksa tidak sesuai dengan permintaan.',
                );

                return;
            }

            foreach ($requestItems as $lineId => $line) {
                $approved = (float) data_get(
                    $submittedItems,
                    "{$lineId}.approved_quantity",
                    0,
                );

                if ($approved > (float) $line->requested_quantity) {
                    $validator->errors()->add(
                        "items.{$lineId}.approved_quantity",
                        'Jumlah disetujui tidak boleh melebihi jumlah yang diminta.',
                    );
                }
            }

            if (
                $submittedItems->sum(
                    static fn (array $line): float => (float) (
                        $line['approved_quantity'] ?? 0
                    ),
                ) <= 0
            ) {
                $validator->errors()->add(
                    'items',
                    'Minimal satu barang harus memiliki jumlah persetujuan lebih dari nol.',
                );
            }
        });
    }
}

<?php

namespace App\Http\Requests;

use App\Models\InventoryRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DeliverInventoryRequestRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*' => ['required', 'array'],
            'items.*.delivered_quantity' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999999.99',
            ],
            'delivery_notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
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
                ->get(['id', 'approved_quantity'])
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
                    'Daftar barang yang diserahkan tidak sesuai dengan permintaan.',
                );

                return;
            }

            foreach ($requestItems as $lineId => $line) {
                $delivered = (float) data_get(
                    $submittedItems,
                    "{$lineId}.delivered_quantity",
                    0,
                );

                if ($delivered > (float) $line->approved_quantity) {
                    $validator->errors()->add(
                        "items.{$lineId}.delivered_quantity",
                        'Jumlah diserahkan tidak boleh melebihi jumlah yang disetujui.',
                    );
                }
            }

            if (
                $submittedItems->sum(
                    static fn (array $line): float => (float) (
                        $line['delivered_quantity'] ?? 0
                    ),
                ) <= 0
            ) {
                $validator->errors()->add(
                    'items',
                    'Minimal satu barang harus diserahkan.',
                );
            }
        });
    }
}

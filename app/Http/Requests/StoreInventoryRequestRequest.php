<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(
                static fn (mixed $line): bool => is_array($line)
                    && filled($line['item_id'] ?? null),
            )
            ->values()
            ->all();

        $this->merge([
            'purpose' => trim((string) $this->input('purpose')),
            'notes' => $this->filled('notes')
                ? trim((string) $this->input('notes'))
                : null,
            'items' => $items,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'request_date' => ['required', 'date_format:Y-m-d'],
            'purpose' => ['required', 'string', 'min:5', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('items', 'id')->where(
                    static fn ($query) => $query->where(
                        'is_active',
                        true,
                    ),
                ),
            ],
            'items.*.requested_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:9999999999999.99',
            ],
            'items.*.notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'request_date' => 'tanggal permintaan',
            'purpose' => 'keperluan',
            'items' => 'daftar barang',
            'items.*.item_id' => 'barang',
            'items.*.requested_quantity' => 'jumlah yang diminta',
            'items.*.notes' => 'catatan barang',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
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
            'name' => trim((string) $this->input('name')),
            'symbol' => mb_strtolower(
                trim((string) $this->input('symbol')),
            ),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unit = $this->route('unit');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('units', 'name')->ignore(
                    $unit instanceof Unit ? $unit->id : null,
                ),
            ],
            'symbol' => [
                'required',
                'string',
                'max:30',
                'regex:/^[\pL\pN._\/-]+$/u',
                Rule::unique('units', 'symbol')->ignore(
                    $unit instanceof Unit ? $unit->id : null,
                ),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}

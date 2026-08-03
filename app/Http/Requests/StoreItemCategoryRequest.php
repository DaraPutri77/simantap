<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Models\ItemCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName::ItemManage->value,
        ) === true;
    }

    protected function prepareForValidation(): void
    {
        $description = trim(
            (string) $this->input('description'),
        );

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'description' => $description === ''
                ? null
                : $description,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('item_category');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('item_categories', 'name')->ignore(
                    $category instanceof ItemCategory
                        ? $category->id
                        : null,
                ),
            ],
            'description' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}

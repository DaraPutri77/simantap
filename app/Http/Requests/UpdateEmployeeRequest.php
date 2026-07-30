<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName::UserManage->value,
        ) === true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        $this->merge([
            'employee_number' => trim(
                (string) $this->input('employee_number'),
            ),
            'name' => trim((string) $this->input('name')),
            'email' => mb_strtolower(
                trim((string) $this->input('email')),
            ),
            'phone' => filled($phone)
                ? preg_replace(
                    '/[\s().-]+/',
                    '',
                    trim((string) $phone),
                )
                : null,
            'work_unit' => trim(
                (string) $this->input('work_unit'),
            ),
            'position' => trim(
                (string) $this->input('position'),
            ),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var User|null $managedUser */
        $managedUser = $this->route('user');

        return [
            'employee_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique(User::class, 'employee_number')
                    ->ignore($managedUser),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique(User::class, 'email')
                    ->ignore($managedUser),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^(?:\+62|62|0)[0-9]{8,13}$/',
            ],
            'work_unit' => [
                'required',
                'string',
                'max:255',
            ],
            'position' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return (new StoreEmployeeRequest)->messages();
    }
}

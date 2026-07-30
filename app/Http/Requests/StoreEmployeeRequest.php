<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName::UserManage->value,
        ) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedInput());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique(User::class, 'employee_number'),
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
                Rule::unique(User::class, 'email'),
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
        return [
            'employee_number.required' => 'NIP atau nomor pegawai wajib diisi.',
            'employee_number.max' => 'NIP atau nomor pegawai maksimal 50 karakter.',
            'employee_number.unique' => 'NIP atau nomor pegawai sudah terdaftar.',
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama lengkap maksimal 255 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email belum valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.unique' => 'Email sudah digunakan akun lain.',
            'phone.max' => 'Nomor telepon maksimal 30 karakter.',
            'phone.regex' => 'Gunakan nomor Indonesia yang valid, misalnya 081234567890 atau +6281234567890.',
            'work_unit.required' => 'Unit kerja wajib diisi.',
            'work_unit.max' => 'Unit kerja maksimal 255 karakter.',
            'position.required' => 'Jabatan wajib diisi.',
            'position.max' => 'Jabatan maksimal 255 karakter.',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function normalizedInput(): array
    {
        $phone = $this->input('phone');

        return [
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
        ];
    }
}

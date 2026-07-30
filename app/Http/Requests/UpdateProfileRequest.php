<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        $this->merge([
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
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
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
                    ->ignore($this->user()),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^(?:\+62|62|0)[0-9]{8,13}$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama lengkap maksimal 255 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email belum valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.unique' => 'Email sudah digunakan oleh akun lain.',
            'phone.max' => 'Nomor telepon maksimal 30 karakter.',
            'phone.regex' => 'Gunakan nomor Indonesia yang valid, misalnya 081234567890 atau +6281234567890.',
        ];
    }
}

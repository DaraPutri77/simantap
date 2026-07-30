<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
                'max:255',
                'current_password:web',
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                'max:255',
                'different:current_password',
                Password::defaults(),
            ],
        ];
    }
}

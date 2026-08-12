<?php

namespace App\Rules;

use App\Support\SignaturePayload;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\ValidationException;

class SignatureDataUrl implements ValidationRule
{
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail,
    ): void {
        if (! is_string($value)) {
            $fail('Tanda tangan digital tidak valid.');

            return;
        }

        try {
            SignaturePayload::decode($value);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['signature_data'][0]
                ?? 'Tanda tangan digital tidak valid.';

            $fail($message);
        }
    }
}

<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SignatureDataUrl implements ValidationRule
{
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail,
    ): void {
        if (! is_string($value)) {
            $fail('Tanda tangan digital wajib dibubuhkan.');

            return;
        }

        if (! str_starts_with($value, 'data:image/png;base64,')) {
            $fail('Format tanda tangan digital tidak valid.');

            return;
        }

        $encoded = substr($value, strlen('data:image/png;base64,'));
        $binary = base64_decode($encoded, true);

        if ($binary === false || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            $fail('Berkas tanda tangan digital tidak valid.');

            return;
        }

        $maxBytes = (int) config(
            'simantap.uploads.signature_max_size_kb',
            2048,
        ) * 1024;

        if (strlen($binary) > $maxBytes) {
            $fail('Ukuran tanda tangan digital terlalu besar.');
        }
    }
}

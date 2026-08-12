<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class SignaturePayload
{
    private const PREFIX = 'data:image/png;base64,';

    private const MAX_WIDTH = 4096;

    private const MAX_HEIGHT = 1024;

    public static function decode(string $dataUrl): string
    {
        if (! str_starts_with($dataUrl, self::PREFIX)) {
            self::fail('Format tanda tangan digital tidak valid.');
        }

        $encoded = substr($dataUrl, strlen(self::PREFIX));
        $maxBytes = max(
            1,
            (int) config('simantap.uploads.signature_max_size_kb', 2048),
        ) * 1024;

        $maxEncodedLength = ((int) ceil($maxBytes / 3)) * 4 + 4;

        if (
            $encoded === ''
            || strlen($encoded) > $maxEncodedLength
        ) {
            self::fail(
                'Ukuran tanda tangan digital melebihi batas yang diizinkan.',
            );
        }

        $binary = base64_decode($encoded, true);

        if (
            $binary === false
            || $binary === ''
            || strlen($binary) > $maxBytes
        ) {
            self::fail(
                $binary !== false && strlen($binary) > $maxBytes
                    ? 'Ukuran tanda tangan digital melebihi batas yang diizinkan.'
                    : 'Berkas tanda tangan digital tidak valid.',
            );
        }

        $imageInfo = @getimagesizefromstring($binary);

        if (
            $imageInfo === false
            || ($imageInfo[2] ?? null) !== IMAGETYPE_PNG
            || ($imageInfo['mime'] ?? null) !== 'image/png'
        ) {
            self::fail(
                'Berkas tanda tangan digital harus berupa PNG yang valid.',
            );
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);

        if (
            $width < 1
            || $height < 1
            || $width > self::MAX_WIDTH
            || $height > self::MAX_HEIGHT
        ) {
            self::fail('Dimensi tanda tangan digital tidak valid.');
        }

        if (
            ! function_exists('imagecreatefromstring')
            || ! function_exists('imagepng')
        ) {
            self::fail(
                'Server tidak dapat memverifikasi berkas tanda tangan digital.',
            );
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            self::fail(
                'Berkas tanda tangan digital rusak atau tidak lengkap.',
            );
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $encodedSuccessfully = @imagepng($image, null, 6);
        $canonical = ob_get_clean();

        unset($image);

        if (
            ! $encodedSuccessfully
            || ! is_string($canonical)
            || $canonical === ''
        ) {
            self::fail(
                'Berkas tanda tangan digital tidak dapat dinormalisasi.',
            );
        }

        if (strlen($canonical) > $maxBytes) {
            self::fail(
                'Ukuran tanda tangan digital hasil validasi melebihi batas yang diizinkan.',
            );
        }

        return $canonical;
    }

    public static function checksumMatches(
        string $binary,
        string $expectedChecksum,
    ): bool {
        if ($expectedChecksum === '') {
            return false;
        }

        $actualChecksum = hash(
            (string) config(
                'simantap.signature.hash_algorithm',
                'sha256',
            ),
            $binary,
        );

        return hash_equals($expectedChecksum, $actualChecksum);
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'signature_data' => $message,
        ]);
    }
}

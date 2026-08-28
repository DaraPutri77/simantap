<?php

namespace App\Support;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

final class AttachmentIntegrity
{
    public static function checksumMatches(
        Attachment $attachment,
    ): bool {
        $expected = strtolower(
            trim((string) $attachment->checksum),
        );

        if (
            ! preg_match(
                '/\A[a-f0-9]{64}\z/',
                $expected,
            )
        ) {
            return false;
        }

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->file_path)) {
            return false;
        }

        $stream = $disk->readStream(
            $attachment->file_path,
        );

        if (! is_resource($stream)) {
            return false;
        }

        try {
            $context = hash_init('sha256');

            hash_update_stream(
                $context,
                $stream,
            );

            $actual = hash_final($context);
        } finally {
            fclose($stream);
        }

        return hash_equals(
            $expected,
            $actual,
        );
    }

    public static function dataUri(
        Attachment $attachment,
    ): ?string {
        if (
            ! $attachment->isImage()
            || ! self::checksumMatches($attachment)
        ) {
            return null;
        }

        $binary = Storage::disk($attachment->disk)
            ->get($attachment->file_path);

        return sprintf(
            'data:%s;base64,%s',
            $attachment->mime_type,
            base64_encode($binary),
        );
    }
}

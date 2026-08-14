<?php

namespace App\Http\Controllers;

use App\Models\DocumentVerification;
use Illuminate\Http\Response;

class DocumentVerificationController extends Controller
{
    public function __invoke(string $token): Response
    {
        $verification = DocumentVerification::query()
            ->where('public_token', $token)
            ->firstOrFail();

        $hasNewerVersion = DocumentVerification::query()
            ->where(
                'document_type',
                $verification->document_type,
            )
            ->where(
                'verifiable_type',
                $verification->verifiable_type,
            )
            ->where(
                'verifiable_id',
                $verification->verifiable_id,
            )
            ->where(
                'version',
                '>',
                $verification->version,
            )
            ->exists();

        $status = $verification->isRevoked()
            ? 'revoked'
            : (
                $hasNewerVersion
                    ? 'superseded'
                    : 'valid'
            );

        $metadata = $verification->public_metadata ?? [];

        return response()
            ->view(
                'document-verifications.show',
                [
                    'verification' => $verification,
                    'verificationStatus' => $status,
                    'documentLabel' => (string) (
                        $metadata['document_label']
                            ?? $verification->document_type
                    ),
                    'institutionName' => (string) config(
                        'simantap.institution.name',
                        'Badan Pusat Statistik Kabupaten Jombang',
                    ),
                    'displayTimezone' => (string) config(
                        'simantap.display_timezone',
                        'Asia/Jakarta',
                    ),
                ],
            )
            ->header(
                'Cache-Control',
                'private, no-store, max-age=0',
            )
            ->header(
                'X-Robots-Tag',
                'noindex, nofollow',
            )
            ->header(
                'X-Content-Type-Options',
                'nosniff',
            );
    }
}

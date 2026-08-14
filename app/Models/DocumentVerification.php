<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class DocumentVerification extends Model
{
    protected $fillable = [
        'public_token',
        'document_type',
        'verifiable_type',
        'verifiable_id',
        'document_reference',
        'version',
        'payload_schema_version',
        'hash_algorithm',
        'payload_hash',
        'public_metadata',
        'issued_by',
        'issued_at',
        'revoked_by',
        'revoked_at',
        'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'payload_schema_version' => 'integer',
            'public_metadata' => 'array',
            'issued_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (
            self $verification,
        ): void {
            $dirty = array_keys(
                $verification->getDirty(),
            );

            sort($dirty);

            $allowed = [
                'revocation_reason',
                'revoked_at',
                'revoked_by',
            ];

            sort($allowed);

            $revocationOnly =
                $verification->getOriginal('revoked_at') === null
                && $verification->revoked_at !== null
                && in_array(
                    'revoked_at',
                    $dirty,
                    true,
                )
                && array_diff(
                    $dirty,
                    $allowed,
                ) === [];

            if ($revocationOnly) {
                return;
            }

            throw new LogicException(
                'Versi verifikasi dokumen tidak boleh diubah.',
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Versi verifikasi dokumen tidak boleh dihapus.',
            );
        });
    }

    public function verifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'issued_by',
        );
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'revoked_by',
        );
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}

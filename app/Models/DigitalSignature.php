<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class DigitalSignature extends Model
{
    protected $fillable = [
        'signable_type',
        'signable_id',
        'signer_id',
        'signer_name_snapshot',
        'employee_number_snapshot',
        'purpose',
        'image_path',
        'transaction_hash',
        'image_checksum',
        'ip_address',
        'user_agent',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException(
                'Tanda tangan digital tidak boleh diubah.',
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Tanda tangan digital tidak boleh dihapus.',
            );
        });
    }

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_id');
    }
}

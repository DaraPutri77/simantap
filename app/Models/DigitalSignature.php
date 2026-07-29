<?php

namespace App\Models;

use App\Enums\DigitalSignaturePurpose;
use App\Models\Concerns\IsImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DigitalSignature extends Model
{
    use IsImmutable;

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
            'purpose' => DigitalSignaturePurpose::class,
            'signed_at' => 'immutable_datetime',
        ];
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

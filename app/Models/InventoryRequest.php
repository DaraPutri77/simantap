<?php

namespace App\Models;

use App\Enums\DigitalSignaturePurpose;
use App\Enums\InventoryRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const DEFAULT_PURPOSE = 'Permintaan persediaan';

    protected $fillable = [
        'request_number',
        'requested_by',
        'employee_number_snapshot',
        'requester_name_snapshot',
        'work_unit_snapshot',
        'request_date',
        'purpose',
        'notes',
        'status',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'revision_note',
        'delivered_by',
        'delivered_at',
        'received_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'expired_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => InventoryRequestStatus::class,
            'request_date' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'delivered_at' => 'datetime',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function deliverer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryRequestItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(InventoryRequestStatusHistory::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function digitalSignatures(): MorphMany
    {
        return $this->morphMany(DigitalSignature::class, 'signable');
    }

    public function signatures(): MorphMany
    {
        return $this->digitalSignatures();
    }

    public function submissionSignature(): ?DigitalSignature
    {
        if (in_array($this->status, [
            InventoryRequestStatus::Draft,
            InventoryRequestStatus::RevisionRequired,
        ], true)) {
            return null;
        }

        return $this->latestSignature(
            DigitalSignaturePurpose::InventoryRequestSubmission,
        );
    }

    public function approvalSignature(): ?DigitalSignature
    {
        return $this->latestSignature(
            DigitalSignaturePurpose::InventoryRequestApproval,
        );
    }

    public function receiptSignature(): ?DigitalSignature
    {
        return $this->latestSignature(
            DigitalSignaturePurpose::InventoryReceiptConfirmation,
        );
    }

    private function latestSignature(
        DigitalSignaturePurpose $purpose,
    ): ?DigitalSignature {
        if ($this->relationLoaded('signatures')) {
            return $this->signatures
                ->filter(
                    static fn (DigitalSignature $signature): bool => $signature
                        ->purpose === $purpose,
                )
                ->sortByDesc('version')
                ->first();
        }

        return $this->signatures()
            ->where('purpose', $purpose->value)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();
    }
}

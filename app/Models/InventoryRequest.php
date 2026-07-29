<?php

namespace App\Models;

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
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'revision_note',
        'delivered_by',
        'delivered_at',
        'received_at',
        'completed_at',
        'cancelled_at',
        'expired_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'datetime',
            'status' => InventoryRequestStatus::class,
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
        return $this->hasMany(InventoryRequestStatusHistory::class)
            ->orderBy('changed_at')
            ->orderBy('id');
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function digitalSignatures(): MorphMany
    {
        return $this->morphMany(DigitalSignature::class, 'signable');
    }

    public function isDraft(): bool
    {
        return $this->status === InventoryRequestStatus::Draft;
    }

    public function requiresRevision(): bool
    {
        return $this->status === InventoryRequestStatus::RevisionRequired;
    }

    public function isCompleted(): bool
    {
        return $this->status === InventoryRequestStatus::Completed;
    }
}

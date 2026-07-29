<?php

namespace App\Models;

use App\Enums\InventoryReceiptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryReceipt extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'source',
        'reference_number',
        'notes',
        'status',
        'created_by',
        'posted_by',
        'posted_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'datetime',
            'status' => InventoryReceiptStatus::class,
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryReceiptItem::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isDraft(): bool
    {
        return $this->status === InventoryReceiptStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === InventoryReceiptStatus::Posted;
    }

    public function isCancelled(): bool
    {
        return $this->status === InventoryReceiptStatus::Cancelled;
    }
}

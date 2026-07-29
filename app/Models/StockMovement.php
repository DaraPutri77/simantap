<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\IsImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use HasFactory;
    use IsImmutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'movement_number',
        'reference_number',
        'item_id',
        'movement_type',
        'reference_type',
        'reference_id',
        'quantity_in',
        'quantity_out',
        'stock_before',
        'stock_after',
        'transaction_date',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
            'quantity_in' => 'decimal:2',
            'quantity_out' => 'decimal:2',
            'stock_before' => 'decimal:2',
            'stock_after' => 'decimal:2',
            'transaction_date' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isInbound(): bool
    {
        return $this->movement_type->isInbound();
    }

    public function isOutbound(): bool
    {
        return $this->movement_type->isOutbound();
    }
}

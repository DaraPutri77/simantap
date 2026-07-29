<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory;
    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'item_code',
        'category_id',
        'unit_id',
        'name',
        'description',
        'minimum_stock',
        'storage_location',
        'image_path',
        'is_active',
    ];

    protected $appends = [
        'available_stock',
        'is_low_stock',
    ];

    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:2',
            'reserved_stock' => 'decimal:2',
            'minimum_stock' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereRaw(
            '(current_stock - reserved_stock) <= minimum_stock',
        );
    }

    protected function availableStock(): Attribute
    {
        return Attribute::get(function (): string {
            $availableStock = max(
                0,
                (float) $this->getAttribute('current_stock')
                    - (float) $this->getAttribute('reserved_stock'),
            );

            return number_format($availableStock, 2, '.', '');
        });
    }

    protected function isLowStock(): Attribute
    {
        return Attribute::get(
            fn (): bool => (float) $this->available_stock
                <= (float) $this->minimum_stock,
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function inventoryReceiptItems(): HasMany
    {
        return $this->hasMany(InventoryReceiptItem::class);
    }

    public function stockAdjustmentItems(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function inventoryRequestItems(): HasMany
    {
        return $this->hasMany(InventoryRequestItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Item extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'item_code',
        'category_id',
        'unit_id',
        'name',
        'harga', // Telah ditambahkan
        'description',
        'current_stock',
        'reserved_stock',
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
            'harga' => 'decimal:2', // Telah ditambahkan
            'current_stock' => 'decimal:2',
            'reserved_stock' => 'decimal:2',
            'minimum_stock' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if ($item->public_id === null || $item->public_id === '') {
                $item->public_id = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected function availableStock(): Attribute
    {
        return Attribute::get(
            fn (): string => number_format(
                max(
                    0,
                    (float) $this->current_stock
                        - (float) $this->reserved_stock,
                ),
                2,
                '.',
                '',
            ),
        );
    }

    protected function isLowStock(): Attribute
    {
        return Attribute::get(
            fn (): bool => (float) $this->available_stock <= (float) $this->minimum_stock,
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(InventoryRequestItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(InventoryReceiptItem::class);
    }

    public function adjustmentItems(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_receipt_id',
        'item_id',
        'item_code_snapshot',
        'item_name_snapshot',
        'unit_snapshot',
        'quantity',
        'unit_cost',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(
            InventoryReceipt::class,
            'inventory_receipt_id',
        );
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_request_id',
        'item_id',
        'item_code_snapshot',
        'item_name_snapshot',
        'unit_snapshot',
        'requested_quantity',
        'approved_quantity',
        'reserved_quantity',
        'delivered_quantity',
        'notes',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:2',
            'approved_quantity' => 'decimal:2',
            'reserved_quantity' => 'decimal:2',
            'delivered_quantity' => 'decimal:2',
        ];
    }

    public function inventoryRequest(): BelongsTo
    {
        return $this->belongsTo(InventoryRequest::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}

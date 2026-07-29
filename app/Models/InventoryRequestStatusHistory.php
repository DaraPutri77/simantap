<?php

namespace App\Models;

use App\Enums\InventoryRequestStatus;
use App\Models\Concerns\IsImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRequestStatusHistory extends Model
{
    use IsImmutable;

    public $timestamps = false;

    protected $fillable = [
        'inventory_request_id',
        'previous_status',
        'new_status',
        'notes',
        'changed_by',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_status' => InventoryRequestStatus::class,
            'new_status' => InventoryRequestStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    public function inventoryRequest(): BelongsTo
    {
        return $this->belongsTo(InventoryRequest::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

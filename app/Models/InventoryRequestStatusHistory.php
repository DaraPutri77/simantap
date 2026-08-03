<?php

namespace App\Models;

use App\Enums\InventoryRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryRequestStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'request_status_histories';

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

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException(
                'Riwayat status permintaan tidak boleh diubah.',
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Riwayat status permintaan tidak boleh dihapus.',
            );
        });
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

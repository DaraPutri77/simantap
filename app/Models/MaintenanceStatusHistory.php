<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MaintenanceStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'maintenance_record_id',
        'previous_status',
        'new_status',
        'notes',
        'changed_by',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_status' => MaintenanceStatus::class,
            'new_status' => MaintenanceStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException(
                'Riwayat status pemeliharaan tidak boleh diubah.',
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Riwayat status pemeliharaan tidak boleh dihapus.',
            );
        });
    }

    public function maintenanceRecord(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

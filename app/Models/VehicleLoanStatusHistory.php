<?php

namespace App\Models;

use App\Enums\VehicleLoanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class VehicleLoanStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'vehicle_loan_id',
        'previous_status',
        'new_status',
        'notes',
        'changed_by',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_status' => VehicleLoanStatus::class,
            'new_status' => VehicleLoanStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException(
                'Riwayat status peminjaman tidak boleh diubah.',
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Riwayat status peminjaman tidak boleh dihapus.',
            );
        });
    }

    public function vehicleLoan(): BelongsTo
    {
        return $this->belongsTo(VehicleLoan::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

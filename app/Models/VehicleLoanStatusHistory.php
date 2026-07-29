<?php

namespace App\Models;

use App\Enums\VehicleLoanStatus;
use App\Models\Concerns\IsImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleLoanStatusHistory extends Model
{
    use IsImmutable;

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

    public function vehicleLoan(): BelongsTo
    {
        return $this->belongsTo(VehicleLoan::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceRecord extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'maintenance_number',
        'vehicle_id',
        'source_vehicle_loan_id',
        'vehicle_snapshot',
        'reported_by',
        'handled_by',
        'maintenance_type',
        'complaint',
        'initial_condition',
        'service_provider',
        'reported_date',
        'start_date',
        'completion_date',
        'cost',
        'result',
        'final_condition',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'reported_date' => 'date',
            'start_date' => 'date',
            'completion_date' => 'date',
            'cost' => 'decimal:2',
            'status' => MaintenanceStatus::class,
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function sourceVehicleLoan(): BelongsTo
    {
        return $this->belongsTo(
            VehicleLoan::class,
            'source_vehicle_loan_id',
        );
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isReported(): bool
    {
        return $this->status === MaintenanceStatus::Reported;
    }

    public function isInProgress(): bool
    {
        return $this->status === MaintenanceStatus::InProgress;
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [
            MaintenanceStatus::Completed,
            MaintenanceStatus::CompletedWithNotes,
        ], true);
    }

    public function requiresFurtherAction(): bool
    {
        return in_array($this->status, [
            MaintenanceStatus::FurtherActionRequired,
            MaintenanceStatus::SeverelyDamaged,
            MaintenanceStatus::Unserviceable,
        ], true);
    }
}

<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use App\Enums\VehicleStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceRecord extends Model
{
    use HasFactory;
    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'maintenance_number',
        'vehicle_id',
        'source_vehicle_loan_id',
        'vehicle_snapshot',
        'vehicle_status_before',
        'reported_by',
        'handled_by',
        'approved_by',
        'approved_at',
        'approval_notes',
        'maintenance_type',
        'complaint',
        'initial_condition',
        'service_provider',
        'reported_date',
        'start_date',
        'started_at',
        'completion_date',
        'completed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
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
            'approved_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cost' => 'decimal:2',
            'status' => MaintenanceStatus::class,
            'vehicle_status_before' => VehicleStatus::class,
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(MaintenanceStatusHistory::class)
            ->orderBy('changed_at')
            ->orderBy('id');
    }

    public function isReported(): bool
    {
        return $this->status === MaintenanceStatus::Reported;
    }

    public function isApproved(): bool
    {
        return $this->status === MaintenanceStatus::Approved;
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

    public function isFinal(): bool
    {
        return in_array($this->status, [
            MaintenanceStatus::Completed,
            MaintenanceStatus::CompletedWithNotes,
            MaintenanceStatus::SeverelyDamaged,
            MaintenanceStatus::Unserviceable,
            MaintenanceStatus::Cancelled,
        ], true);
    }
}

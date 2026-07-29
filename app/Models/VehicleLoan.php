<?php

namespace App\Models;

use App\Enums\VehicleLoanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleLoan extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'loan_number',
        'borrower_id',
        'employee_number_snapshot',
        'borrower_name_snapshot',
        'work_unit_snapshot',
        'phone_snapshot',
        'vehicle_id',
        'vehicle_code_snapshot',
        'license_plate_snapshot',
        'vehicle_name_snapshot',
        'purpose',
        'destination',
        'reason',
        'planned_start_at',
        'planned_end_at',
        'actual_start_at',
        'actual_end_at',
        'overdue_at',
        'status',
        'reviewed_by',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'cancelled_at',
        'cancellation_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'overdue_at' => 'datetime',
            'status' => VehicleLoanStatus::class,
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(VehicleLoanStatusHistory::class)
            ->orderBy('changed_at')
            ->orderBy('id');
    }

    public function conditionChecks(): HasMany
    {
        return $this->hasMany(VehicleConditionCheck::class)
            ->orderBy('checked_at')
            ->orderBy('id');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(
            MaintenanceRecord::class,
            'source_vehicle_loan_id',
        );
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function digitalSignatures(): MorphMany
    {
        return $this->morphMany(DigitalSignature::class, 'signable');
    }

    public function isDraft(): bool
    {
        return $this->status === VehicleLoanStatus::Draft;
    }

    public function isBorrowed(): bool
    {
        return $this->status === VehicleLoanStatus::Borrowed;
    }

    public function isAwaitingReturnInspection(): bool
    {
        return $this->status
            === VehicleLoanStatus::AwaitingReturnInspection;
    }

    public function isCompleted(): bool
    {
        return $this->status === VehicleLoanStatus::Completed;
    }

    public function wasMarkedOverdue(): bool
    {
        return $this->overdue_at !== null;
    }
}

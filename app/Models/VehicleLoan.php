<?php

namespace App\Models;

use App\Enums\ConditionCheckType;
use App\Enums\VehicleLoanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class VehicleLoan extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const PICKUP_SIGNATURE_PURPOSE = 'vehicle_loan_pickup';

    protected $fillable = [
        'loan_number',
        'borrower_id',
        'borrower_name_snapshot',
        'employee_number_snapshot',
        'work_unit_snapshot',
        'vehicle_id',
        'vehicle_code_snapshot',
        'license_plate_snapshot',
        'vehicle_name_snapshot',
        'purpose',
        'destination',
        'reason',
        'phone_snapshot',
        'planned_start_at',
        'planned_end_at',
        'actual_start_at',
        'actual_end_at',
        'overdue_at',
        'status',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'cancelled_at',
        'cancellation_reason',
        'admin_notes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => VehicleLoanStatus::class,
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'overdue_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $vehicleLoan): void {
            $vehicleLoan->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
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

    public function signatures(): MorphMany
    {
        return $this->digitalSignatures();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(VehicleLoanStatusHistory::class)
            ->orderBy('changed_at')
            ->orderBy('id');
    }

    public function submissionSignature(): ?DigitalSignature
    {
        return $this->signatures
            ->firstWhere('purpose', 'vehicle_loan_submission');
    }

    public function pickupSignature(): ?DigitalSignature
    {
        return $this->signatures
            ->firstWhere('purpose', self::PICKUP_SIGNATURE_PURPOSE);
    }

    public function checkoutCheck(): ?VehicleConditionCheck
    {
        if ($this->relationLoaded('conditionChecks')) {
            return $this->conditionChecks
                ->firstWhere('check_type', ConditionCheckType::Checkout);
        }

        return $this->conditionChecks()
            ->where('check_type', ConditionCheckType::Checkout->value)
            ->first();
    }

    public function returnCheck(): ?VehicleConditionCheck
    {
        if ($this->relationLoaded('conditionChecks')) {
            return $this->conditionChecks
                ->firstWhere('check_type', ConditionCheckType::Return);
        }

        return $this->conditionChecks()
            ->where('check_type', ConditionCheckType::Return->value)
            ->first();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->borrower_id === $user->getKey();
    }

    public function isDraft(): bool
    {
        return $this->status === VehicleLoanStatus::Draft;
    }

    public function isReadyForPickup(): bool
    {
        return $this->status === VehicleLoanStatus::ReadyForPickup;
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

    public function isReturnIssue(): bool
    {
        return $this->status === VehicleLoanStatus::ReturnIssue;
    }

    public function wasMarkedOverdue(): bool
    {
        return $this->overdue_at !== null;
    }
}

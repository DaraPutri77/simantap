<?php

namespace App\Models;

use App\Enums\ConditionCheckType;
use App\Enums\VehicleOverallCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class VehicleConditionCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_loan_id',
        'check_type',
        'odometer',
        'fuel_level',
        'overall_condition',
        'body_condition',
        'engine_condition',
        'tire_condition',
        'equipment_condition',
        'damage_notes',
        'checked_by',
        'checked_at',
        'borrower_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'check_type' => ConditionCheckType::class,
            'odometer' => 'decimal:1',
            'fuel_level' => 'integer',
            'overall_condition' => VehicleOverallCondition::class,
            'checked_at' => 'datetime',
            'borrower_confirmed_at' => 'datetime',
        ];
    }

    public function vehicleLoan(): BelongsTo
    {
        return $this->belongsTo(VehicleLoan::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isCheckout(): bool
    {
        return $this->check_type === ConditionCheckType::Checkout;
    }

    public function isReturn(): bool
    {
        return $this->check_type === ConditionCheckType::Return;
    }

    public function isConfirmedByBorrower(): bool
    {
        return $this->borrower_confirmed_at !== null;
    }
}

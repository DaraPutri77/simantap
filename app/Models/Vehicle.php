<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory;
    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'vehicle_code',
        'license_plate',
        'brand',
        'model',
        'year',
        'color',
        'chassis_number',
        'engine_number',
        'current_odometer',
        'status',
        'registration_expiry_date',
        'storage_location',
        'responsible_person',
        'image_path',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'status' => VehicleStatus::class,
            'year' => 'integer',
            'current_odometer' => 'decimal:1',
            'registration_expiry_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function vehicleLoans(): HasMany
    {
        return $this->hasMany(VehicleLoan::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function displayName(): string
    {
        return trim("{$this->brand} {$this->model}");
    }

    public function isAvailable(): bool
    {
        return $this->status === VehicleStatus::Available;
    }

    public function isUnderInspection(): bool
    {
        return $this->status === VehicleStatus::Inspection;
    }

    public function isUnderMaintenance(): bool
    {
        return $this->status === VehicleStatus::Maintenance;
    }

    public function canBeBorrowed(): bool
    {
        return $this->is_active && $this->isAvailable();
    }

    public function registrationState(
        ?CarbonInterface $referenceDate = null,
    ): string {
        if ($this->registration_expiry_date === null) {
            return 'missing';
        }

        $timezone = (string) config(
            'simantap.display_timezone',
            'Asia/Jakarta',
        );
        $reference = $referenceDate === null
            ? CarbonImmutable::now($timezone)->startOfDay()
            : CarbonImmutable::instance($referenceDate)->startOfDay();
        $expiry = CarbonImmutable::parse(
            $this->registration_expiry_date->toDateString(),
            $timezone,
        )->startOfDay();

        if ($expiry->lt($reference)) {
            return 'expired';
        }

        if ($expiry->lte($reference->addDays(30))) {
            return 'expiring';
        }

        return 'valid';
    }
}

<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use App\Models\Concerns\HasPublicId;
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
        'image_path',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'current_odometer' => 'decimal:1',
            'status' => VehicleStatus::class,
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
}

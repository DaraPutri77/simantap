<?php

namespace App\Models;

use App\Enums\OperationalAssetStatus;
use App\Enums\OperationalAssetType;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperationalAsset extends Model
{
    use HasFactory;
    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'asset_code',
        'bmn_code',
        'nup',
        'register_code',
        'type',
        'brand',
        'model',
        'serial_number',
        'acquisition_year',
        'location',
        'responsible_person',
        'status',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => OperationalAssetType::class,
            'status' => OperationalAssetStatus::class,
            'acquisition_year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function displayName(): string
    {
        $identity = trim(implode(' ', array_filter([
            $this->brand,
            $this->model,
        ])));

        return $identity === ''
            ? $this->type->label()
            : $this->type->label().' · '.$identity;
    }

    public function administrativeCode(): string
    {
        if ($this->bmn_code !== null && $this->nup !== null) {
            return $this->bmn_code.' / NUP '.$this->nup;
        }

        return $this->asset_code;
    }

    public function canEnterMaintenance(): bool
    {
        return $this->is_active
            && $this->status !== OperationalAssetStatus::Inactive;
    }
}

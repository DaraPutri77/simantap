<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum MaintenanceSubjectType: string
{
    use HasEnumOptions;

    case Vehicle = 'vehicle';
    case OperationalAsset = 'operational_asset';

    public function label(): string
    {
        return match ($this) {
            self::Vehicle => 'Kendaraan',
            self::OperationalAsset => 'Aset Perangkat',
        };
    }
}

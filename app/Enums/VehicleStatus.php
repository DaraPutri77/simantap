<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum VehicleStatus: string
{
    use HasEnumOptions;

    case Available = 'available';
    case Reserved = 'reserved';
    case Borrowed = 'borrowed';
    case Inspection = 'inspection';
    case Maintenance = 'maintenance';
    case Damaged = 'damaged';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Reserved => 'Dipesan',
            self::Borrowed => 'Sedang Dipinjam',
            self::Inspection => 'Dalam Pemeriksaan',
            self::Maintenance => 'Dalam Pemeliharaan',
            self::Damaged => 'Rusak',
            self::Inactive => 'Tidak Aktif',
        };
    }
}

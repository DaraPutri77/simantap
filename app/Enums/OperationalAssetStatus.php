<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum OperationalAssetStatus: string
{
    use HasEnumOptions;

    case Available = 'available';
    case Inspection = 'inspection';
    case Maintenance = 'maintenance';
    case Damaged = 'damaged';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Inspection => 'Perlu Pemeriksaan',
            self::Maintenance => 'Dalam Pemeliharaan',
            self::Damaged => 'Rusak',
            self::Inactive => 'Tidak Aktif',
        };
    }

    /**
     * Status maintenance dan inactive dikendalikan oleh alur sistem.
     *
     * @return list<self>
     */
    public static function manuallyManagedCases(): array
    {
        return [
            self::Available,
            self::Inspection,
            self::Damaged,
        ];
    }

    /**
     * @return list<string>
     */
    public static function manuallyManagedValues(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::manuallyManagedCases(),
        );
    }
}

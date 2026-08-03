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
            self::Borrowed => 'Dipinjam',
            self::Inspection => 'Perlu Pemeriksaan',
            self::Maintenance => 'Dalam Pemeliharaan',
            self::Damaged => 'Rusak',
            self::Inactive => 'Tidak Aktif',
        };
    }

    /**
     * Status yang boleh dipilih langsung dari master kendaraan.
     * Reserved dan Borrowed hanya boleh diubah oleh alur peminjaman.
     *
     * @return list<self>
     */
    public static function manuallyManagedCases(): array
    {
        return [
            self::Available,
            self::Inspection,
            self::Maintenance,
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

    public function isTransactionControlled(): bool
    {
        return in_array($this, [
            self::Reserved,
            self::Borrowed,
        ], true);
    }
}

<?php

namespace App\Enums;

enum DocumentType: string
{
    case InventoryRequest = 'REQ';
    case VehicleLoan = 'LOAN';
    case Maintenance = 'MTC';
    case StockIn = 'STK-IN';
    case StockAdjustment = 'STK-ADJ';
    case StockMovement = 'MOV';

    public function label(): string
    {
        return match ($this) {
            self::InventoryRequest => 'Permintaan Persediaan',
            self::VehicleLoan => 'Peminjaman Kendaraan',
            self::Maintenance => 'Pemeliharaan Kendaraan',
            self::StockIn => 'Barang Masuk',
            self::StockAdjustment => 'Penyesuaian Stok',
            self::StockMovement => 'Pergerakan Stok',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }
}

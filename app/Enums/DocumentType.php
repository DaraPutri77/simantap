<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum DocumentType: string
{
    use HasEnumOptions;

    case InventoryRequest = 'REQ';
    case VehicleLoan = 'LOAN';
    case Maintenance = 'MTC';
    case InventoryReceipt = 'STK-IN';
    case StockAdjustment = 'STK-ADJ';
    case StockMovement = 'MOV';

    public function label(): string
    {
        return match ($this) {
            self::InventoryRequest => 'Permintaan Barang',
            self::VehicleLoan => 'Peminjaman Kendaraan',
            self::Maintenance => 'Pemeliharaan Kendaraan',
            self::InventoryReceipt => 'Penerimaan Barang',
            self::StockAdjustment => 'Penyesuaian Stok',
            self::StockMovement => 'Pergerakan Stok',
        };
    }
}

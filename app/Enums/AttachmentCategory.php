<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum AttachmentCategory: string
{
    use HasEnumOptions;

    case ItemImage = 'item_image';
    case VehicleImage = 'vehicle_image';
    case VehicleFront = 'vehicle_front';
    case VehicleBack = 'vehicle_back';
    case VehicleLeft = 'vehicle_left';
    case VehicleRight = 'vehicle_right';
    case Odometer = 'odometer';
    case Fuel = 'fuel';
    case Damage = 'damage';
    case Receipt = 'receipt';
    case Document = 'document';
    case MaintenanceBefore = 'maintenance_before';
    case MaintenanceAfter = 'maintenance_after';

    public function label(): string
    {
        return match ($this) {
            self::ItemImage => 'Foto Barang',
            self::VehicleImage => 'Foto Kendaraan',
            self::VehicleFront => 'Kendaraan Tampak Depan',
            self::VehicleBack => 'Kendaraan Tampak Belakang',
            self::VehicleLeft => 'Kendaraan Tampak Kiri',
            self::VehicleRight => 'Kendaraan Tampak Kanan',
            self::Odometer => 'Foto Odometer',
            self::Fuel => 'Foto Indikator Bahan Bakar',
            self::Damage => 'Foto Kerusakan',
            self::Receipt => 'Bukti Penerimaan',
            self::Document => 'Dokumen Pendukung',
            self::MaintenanceBefore => 'Kondisi Sebelum Pemeliharaan',
            self::MaintenanceAfter => 'Kondisi Setelah Pemeliharaan',
        };
    }
}

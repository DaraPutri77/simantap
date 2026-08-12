<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum DigitalSignaturePurpose: string
{
    use HasEnumOptions;

    case InventoryRequestSubmission = 'inventory_request_submission';
    case InventoryRequestApproval = 'inventory_request_approval';
    case InventoryReceiptConfirmation = 'inventory_receipt_confirmation';
    case VehicleLoanSubmission = 'vehicle_loan_submission';
    case VehicleLoanApproval = 'vehicle_loan_approval';
    case VehicleLoanPickup = 'vehicle_loan_pickup';
    case VehicleCheckoutConfirmation = 'vehicle_checkout_confirmation';
    case VehicleReturnConfirmation = 'vehicle_return_confirmation';

    public function label(): string
    {
        return match ($this) {
            self::InventoryRequestSubmission => 'Pengajuan Permintaan Barang',
            self::InventoryRequestApproval => 'Persetujuan Permintaan Barang',
            self::InventoryReceiptConfirmation => 'Konfirmasi Penerimaan Barang',
            self::VehicleLoanSubmission => 'Pengajuan Peminjaman Kendaraan',
            self::VehicleLoanApproval => 'Persetujuan Peminjaman Kendaraan',
            self::VehicleLoanPickup => 'Konfirmasi Pengambilan Kendaraan',
            self::VehicleCheckoutConfirmation => 'Konfirmasi Pengambilan Kendaraan',
            self::VehicleReturnConfirmation => 'Konfirmasi Pengembalian Kendaraan',
        };
    }
}

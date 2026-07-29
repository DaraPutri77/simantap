<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum DigitalSignaturePurpose: string
{
    use HasEnumOptions;

    case InventoryRequestSubmission = 'inventory_request_submission';
    case InventoryReceiptConfirmation = 'inventory_receipt_confirmation';
    case VehicleLoanSubmission = 'vehicle_loan_submission';
    case VehicleCheckoutConfirmation = 'vehicle_checkout_confirmation';
    case VehicleReturnConfirmation = 'vehicle_return_confirmation';

    public function label(): string
    {
        return match ($this) {
            self::InventoryRequestSubmission => 'Pengajuan Permintaan Barang',
            self::InventoryReceiptConfirmation => 'Konfirmasi Penerimaan Barang',
            self::VehicleLoanSubmission => 'Pengajuan Peminjaman Kendaraan',
            self::VehicleCheckoutConfirmation => 'Konfirmasi Pengambilan Kendaraan',
            self::VehicleReturnConfirmation => 'Konfirmasi Pengembalian Kendaraan',
        };
    }
}

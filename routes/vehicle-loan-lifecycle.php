<?php

use App\Http\Controllers\VehicleLoanLifecycleController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'active',
    'password.changed',
])->group(function (): void {
    Route::prefix('operasional-kendaraan')
        ->name('vehicle-loan-lifecycle.admin.')
        ->middleware('permission:vehicle-loan.check')
        ->group(function (): void {
            Route::get('/', [VehicleLoanLifecycleController::class, 'adminIndex'])
                ->name('index');
            Route::post(
                '/{vehicleLoan}/checkout',
                [VehicleLoanLifecycleController::class, 'storeCheckout'],
            )->name('checkout');
            Route::post(
                '/{vehicleLoan}/pemeriksaan-pengembalian',
                [VehicleLoanLifecycleController::class, 'storeReturn'],
            )->name('return-inspection');
        });

    Route::prefix('pengembalian-kendaraan')
        ->name('vehicle-loan-lifecycle.employee.')
        ->middleware('permission:vehicle-loan.return')
        ->group(function (): void {
            Route::get('/', [VehicleLoanLifecycleController::class, 'employeeIndex'])
                ->name('index');
            Route::post(
                '/{vehicleLoan}/konfirmasi-pengambilan',
                [VehicleLoanLifecycleController::class, 'confirmPickup'],
            )->name('confirm-pickup');
            Route::post(
                '/{vehicleLoan}/ajukan-pengembalian',
                [VehicleLoanLifecycleController::class, 'requestReturn'],
            )->name('request-return');
        });

    Route::get(
        '/dokumen-operasional-kendaraan/{vehicleLoan}/pdf',
        [VehicleLoanLifecycleController::class, 'downloadPdf'],
    )->name('vehicle-loan-lifecycle.pdf');

    Route::get(
        '/bukti-kondisi-kendaraan/{vehicleLoan}/{attachment}',
        [VehicleLoanLifecycleController::class, 'evidence'],
    )->name('vehicle-loan-lifecycle.evidence');
});

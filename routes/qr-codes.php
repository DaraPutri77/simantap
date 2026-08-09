<?php

use App\Http\Controllers\QrCodeController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'active',
    'password.changed',
    'role:admin',
])->prefix('qr-code')
    ->name('qr-codes.')
    ->group(function (): void {
        Route::middleware('permission:item.manage')
            ->prefix('barang/{item}')
            ->name('item.')
            ->group(function (): void {
                Route::get('/svg', [QrCodeController::class, 'itemSvg'])
                    ->name('svg');
                Route::get('/label', [QrCodeController::class, 'itemLabel'])
                    ->name('label');
            });

        Route::middleware('permission:vehicle.manage')
            ->prefix('kendaraan/{vehicle}')
            ->name('vehicle.')
            ->group(function (): void {
                Route::get('/svg', [QrCodeController::class, 'vehicleSvg'])
                    ->name('svg');
                Route::get('/label', [QrCodeController::class, 'vehicleLabel'])
                    ->name('label');
            });
    });

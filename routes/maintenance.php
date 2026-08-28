<?php

use App\Http\Controllers\MaintenanceRecordController;
use App\Http\Controllers\OperationalAssetController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'active',
    'password.changed',
    'role:admin',
    'permission:maintenance.view',
])->prefix('pemeliharaan')
    ->group(function (): void {
        Route::prefix('aset-perangkat')
            ->name('operational-assets.')
            ->group(function (): void {
                Route::get('/', [OperationalAssetController::class, 'index'])
                    ->name('index');

                Route::middleware('permission:maintenance.manage')
                    ->group(function (): void {
                        Route::get('/tambah', [OperationalAssetController::class, 'create'])
                            ->name('create');
                        Route::post('/', [OperationalAssetController::class, 'store'])
                            ->name('store');
                        Route::get('/{operationalAsset}/edit', [OperationalAssetController::class, 'edit'])
                            ->whereUuid('operationalAsset')
                            ->name('edit');
                        Route::put('/{operationalAsset}', [OperationalAssetController::class, 'update'])
                            ->whereUuid('operationalAsset')
                            ->name('update');
                        Route::patch('/{operationalAsset}/nonaktifkan', [OperationalAssetController::class, 'deactivate'])
                            ->whereUuid('operationalAsset')
                            ->name('deactivate');
                        Route::patch('/{operationalAsset}/aktifkan', [OperationalAssetController::class, 'activate'])
                            ->whereUuid('operationalAsset')
                            ->name('activate');
                    });

                Route::get('/{operationalAsset}', [OperationalAssetController::class, 'show'])
                    ->whereUuid('operationalAsset')
                    ->name('show');
            });

        Route::name('maintenance-records.')
            ->group(function (): void {
                Route::get('/', [MaintenanceRecordController::class, 'index'])
                    ->name('index');

                Route::middleware('permission:maintenance.manage')
                    ->group(function (): void {
                        Route::get('/tambah', [MaintenanceRecordController::class, 'create'])
                            ->name('create');
                        Route::get(
                            '/dari-pengembalian/{vehicleLoan}',
                            [MaintenanceRecordController::class, 'createFromLoan'],
                        )->whereUuid('vehicleLoan')
                            ->name('create-from-loan');
                        Route::post('/', [MaintenanceRecordController::class, 'store'])
                            ->name('store');

                        Route::post(
                            '/{maintenanceRecord}/setujui',
                            [MaintenanceRecordController::class, 'approve'],
                        )->whereUuid('maintenanceRecord')
                            ->name('approve');
                        Route::post(
                            '/{maintenanceRecord}/mulai',
                            [MaintenanceRecordController::class, 'start'],
                        )->whereUuid('maintenanceRecord')
                            ->name('start');
                        Route::post(
                            '/{maintenanceRecord}/selesaikan',
                            [MaintenanceRecordController::class, 'complete'],
                        )->whereUuid('maintenanceRecord')
                            ->name('complete');
                        Route::post(
                            '/{maintenanceRecord}/batalkan',
                            [MaintenanceRecordController::class, 'cancel'],
                        )->whereUuid('maintenanceRecord')
                            ->name('cancel');
                    });

                Route::get(
                    '/{maintenanceRecord}/bukti/{attachment}',
                    [MaintenanceRecordController::class, 'evidence'],
                )->whereUuid('maintenanceRecord')
                    ->whereNumber('attachment')
                    ->name('evidence');

                Route::get(
                    '/{maintenanceRecord}',
                    [MaintenanceRecordController::class, 'show'],
                )->whereUuid('maintenanceRecord')
                    ->name('show');
            });
    });

<?php

use App\Http\Controllers\MaintenanceRecordController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'active',
    'password.changed',
    'role:admin',
    'permission:maintenance.view',
])->prefix('pemeliharaan')
    ->name('maintenance-records.')
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
                )->name('create-from-loan');
                Route::post('/', [MaintenanceRecordController::class, 'store'])
                    ->name('store');

                Route::post(
                    '/{maintenanceRecord}/setujui',
                    [MaintenanceRecordController::class, 'approve'],
                )->name('approve');
                Route::post(
                    '/{maintenanceRecord}/mulai',
                    [MaintenanceRecordController::class, 'start'],
                )->name('start');
                Route::post(
                    '/{maintenanceRecord}/selesaikan',
                    [MaintenanceRecordController::class, 'complete'],
                )->name('complete');
                Route::post(
                    '/{maintenanceRecord}/batalkan',
                    [MaintenanceRecordController::class, 'cancel'],
                )->name('cancel');
            });

        Route::get(
            '/{maintenanceRecord}/bukti/{attachment}',
            [MaintenanceRecordController::class, 'evidence'],
        )->name('evidence');

        Route::get(
            '/{maintenanceRecord}',
            [MaintenanceRecordController::class, 'show'],
        )->name('show');
    });

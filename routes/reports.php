<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'active',
    'password.changed',
    'role:admin',
    'permission:report.view',
])->prefix('laporan')
    ->name('reports.')
    ->group(function (): void {
        Route::get('/', [ReportController::class, 'index'])
            ->name('index');

        Route::get('/{report}/pdf', [ReportController::class, 'downloadPdf'])
            ->middleware('permission:report.export')
            ->name('pdf');

        Route::get('/{report}/excel', [ReportController::class, 'downloadExcel'])
            ->middleware('permission:report.export')
            ->name('excel');
    });

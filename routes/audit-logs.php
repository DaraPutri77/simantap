<?php

use App\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'active',
    'password.changed',
    'role:admin',
    'permission:audit-log.view',
])->prefix('audit-log')
    ->name('audit-logs.')
    ->group(function (): void {
        Route::get('/', [AuditLogController::class, 'index'])
            ->name('index');
        Route::get('/{auditLog}', [AuditLogController::class, 'show'])
            ->name('show');
    });

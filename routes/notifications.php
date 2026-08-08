<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'active',
    'password.changed',
])->prefix('notifikasi')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])
            ->name('index');

        Route::post('/tandai-semua-dibaca', [NotificationController::class, 'markAllRead'])
            ->name('read-all');

        Route::post('/{notification}/baca', [NotificationController::class, 'markRead'])
            ->name('read');

        Route::get('/{notification}', [NotificationController::class, 'open'])
            ->name('open');
    });

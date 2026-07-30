<?php

use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(
    function (): void {
        Route::get(
            '/login',
            [
                AuthenticatedSessionController::class,
                'create',
            ],
        )->name('login');

        Route::post(
            '/login',
            [
                AuthenticatedSessionController::class,
                'store',
            ],
        )
            ->middleware('throttle:20,1')
            ->name('login.store');

        Route::get(
            '/aktivasi-akun/{token}',
            [
                ActivationController::class,
                'show',
            ],
        )->name('activation.show');

        Route::post(
            '/aktivasi-akun',
            [
                ActivationController::class,
                'store',
            ],
        )
            ->middleware('throttle:10,1')
            ->name('activation.store');

        Route::get(
            '/lupa-kata-sandi',
            [
                PasswordResetLinkController::class,
                'create',
            ],
        )->name('password.request');

        Route::post(
            '/lupa-kata-sandi',
            [
                PasswordResetLinkController::class,
                'store',
            ],
        )
            ->middleware('throttle:5,1')
            ->name('password.email');

        Route::get(
            '/reset-kata-sandi/{token}',
            [
                NewPasswordController::class,
                'create',
            ],
        )->name('password.reset');

        Route::post(
            '/reset-kata-sandi',
            [
                NewPasswordController::class,
                'store',
            ],
        )
            ->middleware('throttle:10,1')
            ->name('password.store');
    },
);

Route::middleware([
    'auth',
    'active',
])->group(function (): void {
    Route::get(
        '/ubah-kata-sandi',
        [
            PasswordController::class,
            'edit',
        ],
    )->name('password.change');

    Route::put(
        '/ubah-kata-sandi',
        [
            PasswordController::class,
            'update',
        ],
    )->name('password.update');

    Route::post(
        '/logout',
        [
            AuthenticatedSessionController::class,
            'destroy',
        ],
    )->name('logout');

    Route::middleware('password.changed')->group(
        function (): void {
            Route::redirect('/', '/dashboard');

            Route::get(
                '/dashboard',
                [
                    DashboardController::class,
                    'index',
                ],
            )
                ->middleware('permission:dashboard.view')
                ->name('dashboard');
        },
    );
});

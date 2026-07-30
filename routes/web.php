<?php

use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('login.store');

    Route::get('/aktivasi-akun/{token}', [ActivationController::class, 'show'])
        ->name('activation.show');
    Route::post('/aktivasi-akun', [ActivationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('activation.store');

    Route::get('/lupa-kata-sandi', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/lupa-kata-sandi', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::get('/reset-kata-sandi/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-kata-sandi', [NewPasswordController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('password.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/ubah-kata-sandi', [PasswordController::class, 'edit'])
        ->name('password.change');
    Route::put('/ubah-kata-sandi', [PasswordController::class, 'update'])
        ->name('password.update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::middleware('password.changed')->group(function (): void {
        Route::redirect('/', '/dashboard');
        Route::get('/dashboard', DashboardController::class)
            ->middleware('permission:dashboard.view')
            ->name('dashboard');
        Route::get('/profil', [ProfileController::class, 'show'])
            ->middleware('permission:dashboard.view')
            ->name('profile.show');
        Route::get('/profil/edit', [ProfileController::class, 'edit'])
            ->middleware('permission:dashboard.view')
            ->name('profile.edit');
        Route::put('/profil', [ProfileController::class, 'update'])
            ->middleware('permission:dashboard.view')
            ->name('profile.update');

        Route::middleware([
            'role:admin',
            'permission:user.view',
        ])->prefix('manajemen-pengguna')
            ->name('users.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [UserManagementController::class, 'index'],
                )->name('index');

                Route::middleware('permission:user.manage')
                    ->group(function (): void {
                        Route::get(
                            '/tambah',
                            [UserManagementController::class, 'create'],
                        )->name('create');
                        Route::post(
                            '/',
                            [UserManagementController::class, 'store'],
                        )->name('store');
                        Route::get(
                            '/{user}/edit',
                            [UserManagementController::class, 'edit'],
                        )->name('edit');
                        Route::put(
                            '/{user}',
                            [UserManagementController::class, 'update'],
                        )->name('update');
                        Route::post(
                            '/{user}/kirim-ulang-aktivasi',
                            [
                                UserManagementController::class,
                                'resendActivation',
                            ],
                        )->name('activation.resend');
                        Route::patch(
                            '/{user}/nonaktifkan',
                            [UserManagementController::class, 'suspend'],
                        )->name('suspend');
                        Route::patch(
                            '/{user}/aktifkan',
                            [UserManagementController::class, 'reactivate'],
                        )->name('reactivate');
                        Route::post(
                            '/{user}/reset-kata-sandi',
                            [
                                UserManagementController::class,
                                'sendPasswordReset',
                            ],
                        )->name('password-reset.send');
                    });

                Route::middleware('permission:user.import')
                    ->group(function (): void {
                        Route::get(
                            '/impor',
                            [
                                UserManagementController::class,
                                'importForm',
                            ],
                        )->name('import');
                        Route::get(
                            '/impor/template',
                            [
                                UserManagementController::class,
                                'downloadImportTemplate',
                            ],
                        )->name('import.template');
                        Route::post(
                            '/impor',
                            [
                                UserManagementController::class,
                                'import',
                            ],
                        )->name('import.store');
                    });

                Route::get(
                    '/{user}',
                    [UserManagementController::class, 'show'],
                )->name('show');
            });
    });
});

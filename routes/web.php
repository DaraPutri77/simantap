<?php

use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryReceiptController;
use App\Http\Controllers\InventoryRequestApprovalController;
use App\Http\Controllers\InventoryRequestController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleLoanApprovalController;
use App\Http\Controllers\VehicleLoanController;
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

        Route::prefix('persediaan')->group(function (): void {
            Route::middleware('permission:item.view')
                ->group(function (): void {
                    Route::get(
                        '/barang',
                        [ItemController::class, 'index'],
                    )->name('items.index');
                });

            Route::middleware([
                'role:admin',
                'permission:item.manage',
            ])->group(function (): void {
                Route::get(
                    '/barang/tambah',
                    [ItemController::class, 'create'],
                )->name('items.create');
                Route::post(
                    '/barang',
                    [ItemController::class, 'store'],
                )->name('items.store');
                Route::get(
                    '/barang/{item}/edit',
                    [ItemController::class, 'edit'],
                )->name('items.edit');
                Route::put(
                    '/barang/{item}',
                    [ItemController::class, 'update'],
                )->name('items.update');
                Route::patch(
                    '/barang/{item}/nonaktifkan',
                    [ItemController::class, 'deactivate'],
                )->name('items.deactivate');
                Route::patch(
                    '/barang/{item}/aktifkan',
                    [ItemController::class, 'activate'],
                )->name('items.activate');

                Route::get(
                    '/kategori',
                    [ItemCategoryController::class, 'index'],
                )->name('item-categories.index');
                Route::get(
                    '/kategori/tambah',
                    [ItemCategoryController::class, 'create'],
                )->name('item-categories.create');
                Route::post(
                    '/kategori',
                    [ItemCategoryController::class, 'store'],
                )->name('item-categories.store');
                Route::get(
                    '/kategori/{item_category}/edit',
                    [ItemCategoryController::class, 'edit'],
                )->name('item-categories.edit');
                Route::put(
                    '/kategori/{item_category}',
                    [ItemCategoryController::class, 'update'],
                )->name('item-categories.update');

                Route::get(
                    '/satuan',
                    [UnitController::class, 'index'],
                )->name('units.index');
                Route::get(
                    '/satuan/tambah',
                    [UnitController::class, 'create'],
                )->name('units.create');
                Route::post(
                    '/satuan',
                    [UnitController::class, 'store'],
                )->name('units.store');
                Route::get(
                    '/satuan/{unit}/edit',
                    [UnitController::class, 'edit'],
                )->name('units.edit');
                Route::put(
                    '/satuan/{unit}',
                    [UnitController::class, 'update'],
                )->name('units.update');
            });

            Route::middleware([
                'role:admin',
                'permission:stock.view',
            ])->group(function (): void {
                Route::get(
                    '/kartu-stok',
                    [StockMovementController::class, 'index'],
                )->name('stock.index');
                Route::get(
                    '/kartu-stok/{stock_movement}',
                    [StockMovementController::class, 'show'],
                )->name('stock.show');
            });

            Route::middleware([
                'role:admin',
                'permission:stock.manage',
            ])->group(function (): void {
                Route::get(
                    '/barang-masuk',
                    [InventoryReceiptController::class, 'index'],
                )->name('inventory-receipts.index');
                Route::get(
                    '/barang-masuk/tambah',
                    [InventoryReceiptController::class, 'create'],
                )->name('inventory-receipts.create');
                Route::post(
                    '/barang-masuk',
                    [InventoryReceiptController::class, 'store'],
                )->name('inventory-receipts.store');
                Route::get(
                    '/barang-masuk/{inventory_receipt}/edit',
                    [InventoryReceiptController::class, 'edit'],
                )->name('inventory-receipts.edit');
                Route::put(
                    '/barang-masuk/{inventory_receipt}',
                    [InventoryReceiptController::class, 'update'],
                )->name('inventory-receipts.update');
                Route::post(
                    '/barang-masuk/{inventory_receipt}/posting',
                    [InventoryReceiptController::class, 'post'],
                )->name('inventory-receipts.post');
                Route::patch(
                    '/barang-masuk/{inventory_receipt}/batalkan',
                    [InventoryReceiptController::class, 'cancel'],
                )->name('inventory-receipts.cancel');
                Route::get(
                    '/barang-masuk/{inventory_receipt}',
                    [InventoryReceiptController::class, 'show'],
                )->name('inventory-receipts.show');

                Route::get(
                    '/penyesuaian-stok',
                    [StockAdjustmentController::class, 'index'],
                )->name('stock-adjustments.index');
                Route::get(
                    '/penyesuaian-stok/tambah',
                    [StockAdjustmentController::class, 'create'],
                )->name('stock-adjustments.create');
                Route::post(
                    '/penyesuaian-stok',
                    [StockAdjustmentController::class, 'store'],
                )->name('stock-adjustments.store');
                Route::get(
                    '/penyesuaian-stok/{stock_adjustment}/edit',
                    [StockAdjustmentController::class, 'edit'],
                )->name('stock-adjustments.edit');
                Route::put(
                    '/penyesuaian-stok/{stock_adjustment}',
                    [StockAdjustmentController::class, 'update'],
                )->name('stock-adjustments.update');
                Route::post(
                    '/penyesuaian-stok/{stock_adjustment}/posting',
                    [StockAdjustmentController::class, 'post'],
                )->name('stock-adjustments.post');
                Route::patch(
                    '/penyesuaian-stok/{stock_adjustment}/batalkan',
                    [StockAdjustmentController::class, 'cancel'],
                )->name('stock-adjustments.cancel');
                Route::get(
                    '/penyesuaian-stok/{stock_adjustment}',
                    [StockAdjustmentController::class, 'show'],
                )->name('stock-adjustments.show');
            });

            Route::middleware('permission:item.view')
                ->get(
                    '/barang/{item}',
                    [ItemController::class, 'show'],
                )->name('items.show');
        });

        Route::middleware([
            'role:admin',
            'permission:inventory-request.view-all',
        ])->prefix('permintaan-barang')
            ->name('inventory-requests.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [InventoryRequestController::class, 'index'],
                )->name('index');
                Route::get(
                    '/persetujuan',
                    InventoryRequestApprovalController::class,
                )->middleware(
                    'permission:inventory-request.approve',
                )->name('approval-queue');
                Route::get(
                    '/{inventory_request}/pdf',
                    [InventoryRequestController::class, 'downloadPdf'],
                )->name('pdf');

                Route::middleware(
                    'permission:inventory-request.approve',
                )->group(function (): void {
                    Route::post(
                        '/{inventory_request}/mulai-pemeriksaan',
                        [
                            InventoryRequestController::class,
                            'startReview',
                        ],
                    )->name('review');
                    Route::post(
                        '/{inventory_request}/setujui',
                        [InventoryRequestController::class, 'approve'],
                    )->name('approve');
                    Route::post(
                        '/{inventory_request}/minta-perbaikan',
                        [
                            InventoryRequestController::class,
                            'requestRevision',
                        ],
                    )->name('revision');
                    Route::post(
                        '/{inventory_request}/tolak',
                        [InventoryRequestController::class, 'reject'],
                    )->name('reject');
                    Route::post(
                        '/{inventory_request}/menunggu-stok',
                        [InventoryRequestController::class, 'awaitStock'],
                    )->name('await-stock');
                });

                Route::middleware(
                    'permission:inventory-request.deliver',
                )->group(function (): void {
                    Route::post(
                        '/{inventory_request}/siap-diserahkan',
                        [InventoryRequestController::class, 'markReady'],
                    )->name('ready');
                    Route::post(
                        '/{inventory_request}/serahkan',
                        [InventoryRequestController::class, 'deliver'],
                    )->name('deliver');
                });

                Route::patch(
                    '/{inventory_request}/batalkan',
                    [InventoryRequestController::class, 'cancel'],
                )->name('cancel');
                Route::get(
                    '/{inventory_request}',
                    [InventoryRequestController::class, 'show'],
                )->name('show');
            });

        Route::middleware([
            'role:pegawai',
            'permission:inventory-request.view-own',
        ])->prefix('permintaan-saya')
            ->name('my.inventory-requests.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [InventoryRequestController::class, 'index'],
                )->name('index');
                Route::middleware(
                    'permission:inventory-request.create',
                )->group(function (): void {
                    Route::get(
                        '/tambah',
                        [InventoryRequestController::class, 'create'],
                    )->name('create');
                    Route::post(
                        '/',
                        [InventoryRequestController::class, 'store'],
                    )->name('store');
                });
                Route::get(
                    '/{inventory_request}/pdf',
                    [InventoryRequestController::class, 'downloadPdf'],
                )->name('pdf');
                Route::middleware(
                    'permission:inventory-request.update-own',
                )->group(function (): void {
                    Route::get(
                        '/{inventory_request}/edit',
                        [InventoryRequestController::class, 'edit'],
                    )->name('edit');
                    Route::put(
                        '/{inventory_request}',
                        [InventoryRequestController::class, 'update'],
                    )->name('update');
                    Route::post(
                        '/{inventory_request}/ajukan',
                        [InventoryRequestController::class, 'submit'],
                    )->name('submit');
                    Route::patch(
                        '/{inventory_request}/batalkan',
                        [InventoryRequestController::class, 'cancel'],
                    )->name('cancel');
                });
                Route::post(
                    '/{inventory_request}/konfirmasi-penerimaan',
                    [
                        InventoryRequestController::class,
                        'confirmReceipt',
                    ],
                )->middleware(
                    'permission:inventory-request.receive',
                )->name('confirm-receipt');
                Route::get(
                    '/{inventory_request}',
                    [InventoryRequestController::class, 'show'],
                )->name('show');
            });

        Route::middleware([
            'role:admin',
            'permission:vehicle-loan.view-all',
        ])->prefix('peminjaman-kendaraan')
            ->name('vehicle-loans.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [VehicleLoanController::class, 'index'],
                )->name('index');
                Route::get(
                    '/persetujuan',
                    VehicleLoanApprovalController::class,
                )->middleware(
                    'permission:vehicle-loan.approve',
                )->name('approval-queue');
                Route::get(
                    '/{vehicle_loan}/pdf',
                    [VehicleLoanController::class, 'downloadPdf'],
                )->name('pdf');

                Route::middleware('permission:vehicle-loan.approve')
                    ->group(function (): void {
                        Route::post(
                            '/{vehicle_loan}/mulai-pemeriksaan',
                            [VehicleLoanController::class, 'startReview'],
                        )->name('review');
                        Route::post(
                            '/{vehicle_loan}/setujui',
                            [VehicleLoanController::class, 'approve'],
                        )->name('approve');
                        Route::post(
                            '/{vehicle_loan}/tolak',
                            [VehicleLoanController::class, 'reject'],
                        )->name('reject');
                    });

                Route::patch(
                    '/{vehicle_loan}/batalkan',
                    [VehicleLoanController::class, 'cancel'],
                )->name('cancel');
                Route::get(
                    '/{vehicle_loan}',
                    [VehicleLoanController::class, 'show'],
                )->name('show');
            });

        Route::middleware([
            'role:pegawai',
            'permission:vehicle-loan.view-own',
        ])->prefix('peminjaman-saya')
            ->name('my.vehicle-loans.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [VehicleLoanController::class, 'index'],
                )->name('index');
                Route::middleware('permission:vehicle-loan.create')
                    ->group(function (): void {
                        Route::get(
                            '/tambah',
                            [VehicleLoanController::class, 'create'],
                        )->name('create');
                        Route::post(
                            '/',
                            [VehicleLoanController::class, 'store'],
                        )->name('store');
                    });
                Route::get(
                    '/{vehicle_loan}/pdf',
                    [VehicleLoanController::class, 'downloadPdf'],
                )->name('pdf');
                Route::middleware('permission:vehicle-loan.update-own')
                    ->group(function (): void {
                        Route::get(
                            '/{vehicle_loan}/edit',
                            [VehicleLoanController::class, 'edit'],
                        )->name('edit');
                        Route::put(
                            '/{vehicle_loan}',
                            [VehicleLoanController::class, 'update'],
                        )->name('update');
                        Route::post(
                            '/{vehicle_loan}/ajukan',
                            [VehicleLoanController::class, 'submit'],
                        )->name('submit');
                        Route::patch(
                            '/{vehicle_loan}/batalkan',
                            [VehicleLoanController::class, 'cancel'],
                        )->name('cancel');
                    });
                Route::get(
                    '/{vehicle_loan}',
                    [VehicleLoanController::class, 'show'],
                )->name('show');
            });

        Route::prefix('kendaraan')->group(function (): void {
            Route::middleware('permission:vehicle.view')
                ->group(function (): void {
                    Route::get(
                        '/',
                        [VehicleController::class, 'index'],
                    )->name('vehicles.index');
                });

            Route::middleware([
                'role:admin',
                'permission:vehicle.manage',
            ])->group(function (): void {
                Route::get(
                    '/tambah',
                    [VehicleController::class, 'create'],
                )->name('vehicles.create');
                Route::post(
                    '/',
                    [VehicleController::class, 'store'],
                )->name('vehicles.store');
                Route::get(
                    '/{vehicle}/edit',
                    [VehicleController::class, 'edit'],
                )->name('vehicles.edit');
                Route::put(
                    '/{vehicle}',
                    [VehicleController::class, 'update'],
                )->name('vehicles.update');
                Route::patch(
                    '/{vehicle}/nonaktifkan',
                    [VehicleController::class, 'deactivate'],
                )->name('vehicles.deactivate');
                Route::patch(
                    '/{vehicle}/aktifkan',
                    [VehicleController::class, 'activate'],
                )->name('vehicles.activate');
                Route::get(
                    '/{vehicle}/kartu-kendali',
                    [VehicleController::class, 'downloadControlCard'],
                )->name('vehicles.control-card');
            });

            Route::middleware('permission:vehicle.view')
                ->get(
                    '/{vehicle}',
                    [VehicleController::class, 'show'],
                )->name('vehicles.show');
        });
    });
});

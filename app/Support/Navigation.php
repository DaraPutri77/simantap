<?php

namespace App\Support;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Route;

final class Navigation
{
    /**
     * @return list<array{
     *     label: string,
     *     route: string,
     *     icon: string,
     *     active_patterns: list<string>,
     *     is_active: bool
     * }>
     */
    public static function for(User $user): array
    {
        $items = $user->hasRole(RoleName::Administrator->value)
            ? self::administratorItems()
            : self::employeeItems();

        return array_values(array_map(
            static function (array $item): array {
                $item['is_active'] = collect(
                    $item['active_patterns'],
                )->contains(
                    static fn (string $pattern): bool => request()
                        ->routeIs($pattern),
                );

                unset($item['permission']);

                return $item;
            },
            array_filter(
                $items,
                static fn (array $item): bool => Route::has($item['route'])
                    && (
                        $item['permission'] === null
                        || $user->can($item['permission']->value)
                    ),
            ),
        ));
    }

    /**
     * Menu mengikuti Rancangan Sistem SIMANTAP.
     * Item baru tampil setelah route dan permission-nya siap digunakan.
     *
     * @return list<array{
     *     label: string,
     *     route: string,
     *     icon: string,
     *     active_patterns: list<string>,
     *     permission: PermissionName|null
     * }>
     */
    private static function administratorItems(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'route' => 'dashboard',
                'icon' => 'dashboard',
                'active_patterns' => ['dashboard'],
                'permission' => PermissionName::DashboardView,
            ],
            [
                'label' => 'Manajemen Pengguna',
                'route' => 'users.index',
                'icon' => 'users',
                'active_patterns' => ['users.*'],
                'permission' => PermissionName::UserView,
            ],
            [
                'label' => 'Persediaan',
                'route' => 'items.index',
                'icon' => 'inventory',
                'active_patterns' => [
                    'items.*',
                    'item-categories.*',
                    'units.*',
                    'stock.*',
                    'inventory-receipts.*',
                    'stock-adjustments.*',
                ],
                'permission' => PermissionName::ItemView,
            ],
            [
                'label' => 'Permintaan Barang',
                'route' => 'inventory-requests.index',
                'icon' => 'request',
                'active_patterns' => ['inventory-requests.*'],
                'permission' => PermissionName::InventoryRequestViewAll,
            ],
            [
                'label' => 'Kendaraan',
                'route' => 'vehicles.index',
                'icon' => 'vehicle',
                'active_patterns' => [
                    'vehicles.*',
                ],
                'permission' => PermissionName::VehicleView,
            ],
            [
                'label' => 'Peminjaman Kendaraan',
                'route' => 'vehicle-loans.index',
                'icon' => 'request',
                'active_patterns' => ['vehicle-loans.*'],
                'permission' => PermissionName::VehicleLoanViewAll,
            ],
            [
                'label' => 'Serah Terima Kendaraan',
                'route' => 'vehicle-loan-lifecycle.admin.index',
                'icon' => 'vehicle',
                'active_patterns' => ['vehicle-loan-lifecycle.admin.*'],
                'permission' => PermissionName::VehicleLoanCheck,
            ],
            [
                'label' => 'Aset Perangkat',
                'route' => 'operational-assets.index',
                'icon' => 'maintenance',
                'active_patterns' => ['operational-assets.*'],
                'permission' => PermissionName::MaintenanceView,
            ],
            [
                'label' => 'Pemeliharaan',
                'route' => 'maintenance-records.index',
                'icon' => 'maintenance',
                'active_patterns' => ['maintenance-records.*'],
                'permission' => PermissionName::MaintenanceView,
            ],
            [
                'label' => 'Laporan',
                'route' => 'reports.index',
                'icon' => 'report',
                'active_patterns' => ['reports.*'],
                'permission' => PermissionName::ReportView,
            ],
            [
                'label' => 'Audit Log',
                'route' => 'audit-logs.index',
                'icon' => 'audit',
                'active_patterns' => ['audit-logs.*'],
                'permission' => PermissionName::AuditLogView,
            ],
            [
                'label' => 'Pengaturan',
                'route' => 'settings.index',
                'icon' => 'settings',
                'active_patterns' => ['settings.*'],
                'permission' => PermissionName::SettingManage,
            ],
        ];
    }

    /**
     * Menu mengikuti Rancangan Sistem SIMANTAP.
     * Item baru tampil setelah route dan permission-nya siap digunakan.
     *
     * @return list<array{
     *     label: string,
     *     route: string,
     *     icon: string,
     *     active_patterns: list<string>,
     *     permission: PermissionName|null
     * }>
     */
    private static function employeeItems(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'route' => 'dashboard',
                'icon' => 'dashboard',
                'active_patterns' => ['dashboard'],
                'permission' => PermissionName::DashboardView,
            ],
            [
                'label' => 'Persediaan',
                'route' => 'items.index',
                'icon' => 'inventory',
                'active_patterns' => [
                    'items.*',
                    'stock.*',
                ],
                'permission' => PermissionName::ItemView,
            ],
            [
                'label' => 'Permintaan Saya',
                'route' => 'my.inventory-requests.index',
                'icon' => 'request',
                'active_patterns' => ['my.inventory-requests.*'],
                'permission' => PermissionName::InventoryRequestViewOwn,
            ],
            [
                'label' => 'Kendaraan Tersedia',
                'route' => 'vehicles.index',
                'icon' => 'vehicle',
                'active_patterns' => ['vehicles.*'],
                'permission' => PermissionName::VehicleView,
            ],
            [
                'label' => 'Peminjaman Kendaraan',
                'route' => 'my.vehicle-loans.index',
                'icon' => 'vehicle',
                'active_patterns' => ['my.vehicle-loans.*'],
                'permission' => PermissionName::VehicleLoanViewOwn,
            ],
            [
                'label' => 'Pengembalian Kendaraan',
                'route' => 'vehicle-loan-lifecycle.employee.index',
                'icon' => 'vehicle',
                'active_patterns' => ['vehicle-loan-lifecycle.employee.*'],
                'permission' => PermissionName::VehicleLoanReturn,
            ],
            [
                'label' => 'Profil',
                'route' => 'profile.show',
                'icon' => 'profile',
                'active_patterns' => [
                    'profile.*',
                    'password.change',
                ],
                'permission' => PermissionName::DashboardView,
            ],
        ];
    }
}

<?php

namespace App\Enums;

enum RoleName: string
{
    case Administrator = 'admin';
    case Employee = 'pegawai';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Employee => 'Pegawai',
        };
    }

    /**
     * @return list<string>
     */
    public function permissionValues(): array
    {
        return match ($this) {
            self::Administrator => PermissionName::values(),
            self::Employee => [
                PermissionName::DashboardView->value,
                PermissionName::ItemView->value,
                PermissionName::StockView->value,
                PermissionName::InventoryRequestViewOwn->value,
                PermissionName::InventoryRequestCreate->value,
                PermissionName::InventoryRequestUpdateOwn->value,
                PermissionName::InventoryRequestReceive->value,
                PermissionName::VehicleView->value,
                PermissionName::VehicleLoanViewOwn->value,
                PermissionName::VehicleLoanCreate->value,
                PermissionName::VehicleLoanUpdateOwn->value,
                PermissionName::VehicleLoanReturn->value,
            ],
        };
    }
}

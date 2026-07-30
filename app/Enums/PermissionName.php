<?php

namespace App\Enums;

enum PermissionName: string
{
    case DashboardView = 'dashboard.view';

    case UserView = 'user.view';
    case UserManage = 'user.manage';
    case UserImport = 'user.import';

    case ItemView = 'item.view';
    case ItemManage = 'item.manage';

    case StockView = 'stock.view';
    case StockManage = 'stock.manage';

    case InventoryRequestViewOwn = 'inventory-request.view-own';
    case InventoryRequestViewAll = 'inventory-request.view-all';
    case InventoryRequestCreate = 'inventory-request.create';
    case InventoryRequestUpdateOwn = 'inventory-request.update-own';
    case InventoryRequestApprove = 'inventory-request.approve';
    case InventoryRequestDeliver = 'inventory-request.deliver';
    case InventoryRequestReceive = 'inventory-request.receive';

    case VehicleView = 'vehicle.view';
    case VehicleManage = 'vehicle.manage';

    case VehicleLoanViewOwn = 'vehicle-loan.view-own';
    case VehicleLoanViewAll = 'vehicle-loan.view-all';
    case VehicleLoanCreate = 'vehicle-loan.create';
    case VehicleLoanUpdateOwn = 'vehicle-loan.update-own';
    case VehicleLoanApprove = 'vehicle-loan.approve';
    case VehicleLoanCheck = 'vehicle-loan.check';
    case VehicleLoanReturn = 'vehicle-loan.return';

    case MaintenanceView = 'maintenance.view';
    case MaintenanceManage = 'maintenance.manage';

    case ReportView = 'report.view';
    case ReportExport = 'report.export';

    case AuditLogView = 'audit-log.view';

    case SettingManage = 'setting.manage';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }
}

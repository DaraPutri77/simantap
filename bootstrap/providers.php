<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuditLogServiceProvider;
use App\Providers\MaintenanceServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\VehicleLoanLifecycleServiceProvider;

return [
    AppServiceProvider::class,
    AuditLogServiceProvider::class,
    VehicleLoanLifecycleServiceProvider::class,
    MaintenanceServiceProvider::class,
    NotificationServiceProvider::class,
];

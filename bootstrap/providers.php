<?php

use App\Providers\AppServiceProvider;
use App\Providers\MaintenanceServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\VehicleLoanLifecycleServiceProvider;

return [
    AppServiceProvider::class,
    VehicleLoanLifecycleServiceProvider::class,
    MaintenanceServiceProvider::class,
    NotificationServiceProvider::class,
];

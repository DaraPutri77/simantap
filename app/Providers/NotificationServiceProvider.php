<?php

namespace App\Providers;

use App\Console\Commands\DispatchOperationalNotifications;
use App\Models\InventoryRequest;
use App\Models\Item;
use App\Models\MaintenanceRecord;
use App\Models\VehicleLoan;
use App\Observers\InventoryRequestObserver;
use App\Observers\ItemObserver;
use App\Observers\MaintenanceRecordObserver;
use App\Observers\VehicleLoanObserver;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        InventoryRequest::observe(InventoryRequestObserver::class);
        VehicleLoan::observe(VehicleLoanObserver::class);
        MaintenanceRecord::observe(MaintenanceRecordObserver::class);
        Item::observe(ItemObserver::class);

        $this->loadRoutesFrom(
            base_path('routes/notifications.php'),
        );

        if ($this->app->runningInConsole()) {
            $this->commands([DispatchOperationalNotifications::class]);

            Schedule::command('simantap:notifications:dispatch')
                ->hourly()
                ->withoutOverlapping(10);
        }
    }
}

<?php

namespace App\Observers;

use App\Models\MaintenanceRecord;
use App\Services\NotificationService;

class MaintenanceRecordObserver
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function created(MaintenanceRecord $maintenanceRecord): void
    {
        $this->notifications->maintenanceChanged(
            $maintenanceRecord->loadMissing('sourceVehicleLoan.borrower'),
        );
    }

    public function updated(MaintenanceRecord $maintenanceRecord): void
    {
        if (! $maintenanceRecord->wasChanged('status')) {
            return;
        }

        $this->notifications->maintenanceChanged(
            $maintenanceRecord->loadMissing('sourceVehicleLoan.borrower'),
        );
    }
}

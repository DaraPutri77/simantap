<?php

namespace App\Observers;

use App\Models\VehicleLoan;
use App\Services\NotificationService;

class VehicleLoanObserver
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function created(VehicleLoan $vehicleLoan): void
    {
        $this->notifications->vehicleLoanChanged($vehicleLoan);
    }

    public function updated(VehicleLoan $vehicleLoan): void
    {
        if ($vehicleLoan->wasChanged('status')) {
            $this->notifications->vehicleLoanChanged($vehicleLoan);
        }

        if ($vehicleLoan->wasChanged('overdue_at') && $vehicleLoan->overdue_at !== null) {
            $this->notifications->vehicleLoanOverdue($vehicleLoan);
        }
    }
}

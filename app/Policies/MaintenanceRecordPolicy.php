<?php

namespace App\Policies;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRecord;
use App\Models\User;

class MaintenanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('maintenance.view');
    }

    public function view(User $user, MaintenanceRecord $maintenanceRecord): bool
    {
        return $user->can('maintenance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('maintenance.manage');
    }

    public function approve(User $user, MaintenanceRecord $maintenanceRecord): bool
    {
        return $user->can('maintenance.manage')
            && $maintenanceRecord->status === MaintenanceStatus::Reported;
    }

    public function start(User $user, MaintenanceRecord $maintenanceRecord): bool
    {
        return $user->can('maintenance.manage')
            && in_array($maintenanceRecord->status, [
                MaintenanceStatus::Approved,
                MaintenanceStatus::FurtherActionRequired,
            ], true);
    }

    public function complete(User $user, MaintenanceRecord $maintenanceRecord): bool
    {
        return $user->can('maintenance.manage')
            && $maintenanceRecord->status === MaintenanceStatus::InProgress;
    }

    public function cancel(User $user, MaintenanceRecord $maintenanceRecord): bool
    {
        return $user->can('maintenance.manage')
            && in_array($maintenanceRecord->status, [
                MaintenanceStatus::Reported,
                MaintenanceStatus::Approved,
                MaintenanceStatus::InProgress,
                MaintenanceStatus::FurtherActionRequired,
            ], true);
    }
}

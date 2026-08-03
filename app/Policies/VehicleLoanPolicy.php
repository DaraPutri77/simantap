<?php

namespace App\Policies;

use App\Enums\VehicleLoanStatus;
use App\Models\User;
use App\Models\VehicleLoan;

class VehicleLoanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vehicle-loan.view-own')
            || $user->can('vehicle-loan.view-all');
    }

    public function view(User $user, VehicleLoan $vehicleLoan): bool
    {
        return $user->can('vehicle-loan.view-all')
            || (
                $user->can('vehicle-loan.view-own')
                && $vehicleLoan->isOwnedBy($user)
            );
    }

    public function create(User $user): bool
    {
        return $user->can('vehicle-loan.create');
    }

    public function update(User $user, VehicleLoan $vehicleLoan): bool
    {
        return $user->can('vehicle-loan.update-own')
            && $vehicleLoan->isOwnedBy($user)
            && $vehicleLoan->status->isEditable();
    }

    public function submit(User $user, VehicleLoan $vehicleLoan): bool
    {
        return $this->update($user, $vehicleLoan);
    }

    public function approve(User $user, VehicleLoan $vehicleLoan): bool
    {
        return $user->can('vehicle-loan.approve');
    }

    public function cancel(User $user, VehicleLoan $vehicleLoan): bool
    {
        if ($user->can('vehicle-loan.approve')) {
            return ! $vehicleLoan->status->isFinal()
                && ! in_array($vehicleLoan->status, [
                    VehicleLoanStatus::Borrowed,
                    VehicleLoanStatus::AwaitingReturnInspection,
                    VehicleLoanStatus::ReturnIssue,
                ], true);
        }

        return $user->can('vehicle-loan.update-own')
            && $vehicleLoan->isOwnedBy($user)
            && in_array($vehicleLoan->status, [
                VehicleLoanStatus::Draft,
                VehicleLoanStatus::Submitted,
                VehicleLoanStatus::UnderReview,
                VehicleLoanStatus::Approved,
            ], true);
    }
}

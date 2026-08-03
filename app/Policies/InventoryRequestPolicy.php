<?php

namespace App\Policies;

use App\Enums\InventoryRequestStatus;
use App\Models\InventoryRequest;
use App\Models\User;

class InventoryRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory-request.view-own')
            || $user->can('inventory-request.view-all');
    }

    public function view(User $user, InventoryRequest $inventoryRequest): bool
    {
        return $user->can('inventory-request.view-all')
            || (
                $user->can('inventory-request.view-own')
                && $inventoryRequest->requested_by === $user->getKey()
            );
    }

    public function create(User $user): bool
    {
        return $user->can('inventory-request.create');
    }

    public function update(User $user, InventoryRequest $inventoryRequest): bool
    {
        return $user->can('inventory-request.update-own')
            && $inventoryRequest->requested_by === $user->getKey()
            && in_array($inventoryRequest->status->value, ['draft', 'revision_required'], true);
    }

    public function approve(
        User $user,
        InventoryRequest $inventoryRequest,
    ): bool {
        return $user->can('inventory-request.approve');
    }

    public function submit(User $user, InventoryRequest $inventoryRequest): bool
    {
        return $this->update($user, $inventoryRequest);
    }

    public function cancel(User $user, InventoryRequest $inventoryRequest): bool
    {
        if ($user->can('inventory-request.approve')) {
            return ! $inventoryRequest->status->isFinal();
        }

        return $inventoryRequest->requested_by === $user->getKey()
            && $user->can('inventory-request.update-own')
            && in_array($inventoryRequest->status, [
                InventoryRequestStatus::Draft,
                InventoryRequestStatus::Submitted,
                InventoryRequestStatus::RevisionRequired,
                InventoryRequestStatus::WaitingStock,
            ], true);
    }

    public function deliver(
        User $user,
        InventoryRequest $inventoryRequest,
    ): bool {
        return $user->can('inventory-request.deliver');
    }

    public function receive(User $user, InventoryRequest $inventoryRequest): bool
    {
        return $user->can('inventory-request.receive')
            && $inventoryRequest->requested_by === $user->getKey();
    }
}

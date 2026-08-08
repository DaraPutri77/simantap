<?php

namespace App\Observers;

use App\Models\InventoryRequest;
use App\Services\NotificationService;

class InventoryRequestObserver
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function created(InventoryRequest $inventoryRequest): void
    {
        $this->notifications->inventoryRequestChanged($inventoryRequest);
    }

    public function updated(InventoryRequest $inventoryRequest): void
    {
        if (! $inventoryRequest->wasChanged('status')) {
            return;
        }

        $this->notifications->inventoryRequestChanged($inventoryRequest);
    }
}

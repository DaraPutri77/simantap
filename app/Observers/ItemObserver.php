<?php

namespace App\Observers;

use App\Models\Item;
use App\Services\NotificationService;

class ItemObserver
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function updated(Item $item): void
    {
        if (! $item->wasChanged([
            'current_stock',
            'reserved_stock',
            'minimum_stock',
            'is_active',
        ])) {
            return;
        }

        if (! $item->is_active || ! $item->is_low_stock) {
            return;
        }

        $originalCurrent = (float) $item->getRawOriginal('current_stock');
        $originalReserved = (float) $item->getRawOriginal('reserved_stock');
        $originalMinimum = (float) $item->getRawOriginal('minimum_stock');
        $wasLow = max(0, $originalCurrent - $originalReserved) <= $originalMinimum;

        if ($wasLow && ! $item->wasChanged('is_active')) {
            return;
        }

        $this->notifications->lowStock($item);
    }
}

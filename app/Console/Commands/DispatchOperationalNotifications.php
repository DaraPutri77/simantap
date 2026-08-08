<?php

namespace App\Console\Commands;

use App\Enums\VehicleLoanStatus;
use App\Models\Item;
use App\Models\VehicleLoan;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class DispatchOperationalNotifications extends Command
{
    protected $signature = 'simantap:notifications:dispatch';

    protected $description = 'Mengirim notifikasi operasional SIMANTAP yang bergantung pada waktu dan ambang stok.';

    public function handle(NotificationService $notifications): int
    {
        $now = now();
        $dueSoonUntil = $now->copy()->addDay();
        $dueSoonCount = 0;
        $overdueCount = 0;
        $lowStockCount = 0;

        VehicleLoan::query()
            ->with('borrower')
            ->where('status', VehicleLoanStatus::Borrowed->value)
            ->whereNotNull('planned_end_at')
            ->where('planned_end_at', '>', $now)
            ->where('planned_end_at', '<=', $dueSoonUntil)
            ->orderBy('planned_end_at')
            ->chunkById(100, function ($loans) use (
                $notifications,
                &$dueSoonCount,
            ): void {
                foreach ($loans as $loan) {
                    $notifications->vehicleLoanDueSoon($loan);
                    $dueSoonCount++;
                }
            });

        VehicleLoan::query()
            ->with('borrower')
            ->where('status', VehicleLoanStatus::Borrowed->value)
            ->whereNotNull('planned_end_at')
            ->where('planned_end_at', '<=', $now)
            ->orderBy('planned_end_at')
            ->chunkById(100, function ($loans) use (
                $notifications,
                &$overdueCount,
            ): void {
                foreach ($loans as $loan) {
                    $notifications->vehicleLoanOverdue($loan);
                    $overdueCount++;
                }
            });

        Item::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(200, function ($items) use (
                $notifications,
                &$lowStockCount,
            ): void {
                foreach ($items as $item) {
                    if (! $item->is_low_stock) {
                        continue;
                    }

                    $notifications->lowStock($item);
                    $lowStockCount++;
                }
            });

        $this->info(
            "Notifikasi operasional diproses: {$dueSoonCount} mendekati batas, {$overdueCount} terlambat, {$lowStockCount} stok minimum.",
        );

        return self::SUCCESS;
    }
}

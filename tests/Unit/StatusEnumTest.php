<?php

namespace Tests\Unit;

use App\Enums\AccountStatus;
use App\Enums\InventoryReceiptStatus;
use App\Enums\InventoryRequestStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\StockAdjustmentStatus;
use App\Enums\VehicleLoanStatus;
use App\Enums\VehicleStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StatusEnumTest extends TestCase
{
    /**
     * @param  class-string  $enumClass
     * @param  list<string>  $expectedValues
     */
    #[DataProvider('statusEnumProvider')]
    public function test_status_values_match_database_contract(
        string $enumClass,
        array $expectedValues,
    ): void {
        $this->assertSame($expectedValues, $enumClass::values());
        $this->assertSame(
            $expectedValues,
            array_keys($enumClass::options()),
        );

        foreach ($enumClass::options() as $label) {
            $this->assertNotSame('', trim($label));
        }
    }

    /**
     * @return array<string, array{class-string, list<string>}>
     */
    public static function statusEnumProvider(): array
    {
        return [
            'account status' => [
                AccountStatus::class,
                [
                    'pending_activation',
                    'active',
                    'inactive',
                    'suspended',
                ],
            ],
            'inventory receipt status' => [
                InventoryReceiptStatus::class,
                [
                    'draft',
                    'posted',
                    'cancelled',
                ],
            ],
            'stock adjustment status' => [
                StockAdjustmentStatus::class,
                [
                    'draft',
                    'posted',
                    'cancelled',
                ],
            ],
            'inventory request status' => [
                InventoryRequestStatus::class,
                [
                    'draft',
                    'submitted',
                    'under_review',
                    'revision_required',
                    'approved',
                    'partially_approved',
                    'waiting_stock',
                    'ready_for_delivery',
                    'delivered',
                    'completed',
                    'rejected',
                    'cancelled',
                    'expired',
                ],
            ],
            'vehicle status' => [
                VehicleStatus::class,
                [
                    'available',
                    'reserved',
                    'borrowed',
                    'inspection',
                    'maintenance',
                    'damaged',
                    'inactive',
                ],
            ],
            'vehicle loan status' => [
                VehicleLoanStatus::class,
                [
                    'draft',
                    'submitted',
                    'under_review',
                    'approved',
                    'ready_for_pickup',
                    'borrowed',
                    'awaiting_return_inspection',
                    'completed',
                    'rejected',
                    'cancelled',
                    'return_issue',
                ],
            ],
            'maintenance status' => [
                MaintenanceStatus::class,
                [
                    'reported',
                    'approved',
                    'in_progress',
                    'completed',
                    'completed_with_notes',
                    'further_action_required',
                    'severely_damaged',
                    'unserviceable',
                    'cancelled',
                ],
            ],
        ];
    }
}

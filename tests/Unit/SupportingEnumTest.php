<?php

namespace Tests\Unit;

use App\Enums\AttachmentCategory;
use App\Enums\ConditionCheckType;
use App\Enums\DigitalSignaturePurpose;
use App\Enums\DocumentType;
use App\Enums\StockMovementType;
use App\Enums\VehicleOverallCondition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SupportingEnumTest extends TestCase
{
    /**
     * @param  class-string  $enumClass
     * @param  list<string>  $expectedValues
     */
    #[DataProvider('supportingEnumProvider')]
    public function test_supporting_enum_values_match_database_contract(
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

    public function test_stock_movement_direction_is_determined_correctly(): void
    {
        $this->assertTrue(StockMovementType::InitialStock->isInbound());
        $this->assertTrue(StockMovementType::StockIn->isInbound());
        $this->assertTrue(StockMovementType::AdjustmentIn->isInbound());
        $this->assertTrue(StockMovementType::ReturnIn->isInbound());

        $this->assertTrue(StockMovementType::RequestOut->isOutbound());
        $this->assertTrue(StockMovementType::AdjustmentOut->isOutbound());
        $this->assertTrue(StockMovementType::DamagedOut->isOutbound());

        $this->assertFalse(StockMovementType::StockIn->isOutbound());
        $this->assertFalse(StockMovementType::RequestOut->isInbound());
    }

    /**
     * @return array<string, array{class-string, list<string>}>
     */
    public static function supportingEnumProvider(): array
    {
        return [
            'stock movement type' => [
                StockMovementType::class,
                [
                    'initial_stock',
                    'stock_in',
                    'request_out',
                    'adjustment_in',
                    'adjustment_out',
                    'return_in',
                    'damaged_out',
                ],
            ],
            'attachment category' => [
                AttachmentCategory::class,
                [
                    'item_image',
                    'vehicle_image',
                    'vehicle_front',
                    'vehicle_back',
                    'vehicle_left',
                    'vehicle_right',
                    'odometer',
                    'fuel',
                    'damage',
                    'receipt',
                    'document',
                    'maintenance_before',
                    'maintenance_after',
                ],
            ],
            'digital signature purpose' => [
                DigitalSignaturePurpose::class,
                [
                    'inventory_request_submission',
                    'inventory_receipt_confirmation',
                    'vehicle_loan_submission',
                    'vehicle_checkout_confirmation',
                    'vehicle_return_confirmation',
                ],
            ],
            'condition check type' => [
                ConditionCheckType::class,
                [
                    'checkout',
                    'return',
                ],
            ],
            'vehicle overall condition' => [
                VehicleOverallCondition::class,
                [
                    'good',
                    'needs_attention',
                    'damaged',
                ],
            ],
            'document type' => [
                DocumentType::class,
                [
                    'REQ',
                    'LOAN',
                    'MTC',
                    'STK-IN',
                    'STK-ADJ',
                    'MOV',
                ],
            ],
        ];
    }
}

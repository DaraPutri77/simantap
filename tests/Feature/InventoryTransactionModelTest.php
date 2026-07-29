<?php

namespace Tests\Feature;

use App\Enums\InventoryReceiptStatus;
use App\Enums\InventoryRequestStatus;
use App\Enums\StockAdjustmentStatus;
use App\Enums\StockMovementType;
use App\Models\InventoryReceipt;
use App\Models\InventoryReceiptItem;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestItem;
use App\Models\InventoryRequestStatusHistory;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class InventoryTransactionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_receipt_uses_expected_casts_and_relationships(): void
    {
        $user = User::factory()->create();
        $item = $this->createItem();

        $receipt = InventoryReceipt::query()->create([
            'receipt_number' => 'STK-IN/2026/07/0001',
            'receipt_date' => now(),
            'source' => 'Pengadaan',
            'reference_number' => 'SPK-001/2026',
            'notes' => 'Penerimaan untuk pengujian model.',
            'status' => InventoryReceiptStatus::Draft,
            'created_by' => $user->id,
        ]);

        $receiptItem = InventoryReceiptItem::query()->create([
            'inventory_receipt_id' => $receipt->id,
            'item_id' => $item->id,
            'item_code_snapshot' => $item->item_code,
            'item_name_snapshot' => $item->name,
            'unit_snapshot' => $item->unit->symbol,
            'quantity' => 4,
            'unit_cost' => 25000,
            'notes' => null,
        ]);

        $receipt->load([
            'creator',
            'items.item',
        ]);

        $this->assertSame(
            InventoryReceiptStatus::Draft,
            $receipt->status,
        );
        $this->assertTrue($receipt->isDraft());
        $this->assertFalse($receipt->isPosted());
        $this->assertTrue($receipt->creator->is($user));
        $this->assertCount(1, $receipt->items);
        $this->assertTrue($receipt->items->first()->is($receiptItem));
        $this->assertTrue($receiptItem->item->is($item));
        $this->assertSame('4.00', $receiptItem->quantity);
        $this->assertSame('25000.00', $receiptItem->unit_cost);
    }

    public function test_stock_adjustment_uses_expected_casts_and_relationships(): void
    {
        $user = User::factory()->create();
        $item = $this->createItem();

        $adjustment = StockAdjustment::query()->create([
            'adjustment_number' => 'STK-ADJ/2026/07/0001',
            'adjustment_date' => now(),
            'reason' => 'Hasil stock opname.',
            'notes' => 'Pengujian model penyesuaian stok.',
            'status' => StockAdjustmentStatus::Draft,
            'created_by' => $user->id,
        ]);

        $adjustmentItem = StockAdjustmentItem::query()->create([
            'stock_adjustment_id' => $adjustment->id,
            'item_id' => $item->id,
            'item_code_snapshot' => $item->item_code,
            'item_name_snapshot' => $item->name,
            'unit_snapshot' => $item->unit->symbol,
            'system_quantity' => 10,
            'physical_quantity' => 8,
            'difference_quantity' => -2,
            'notes' => 'Selisih hasil pemeriksaan fisik.',
        ]);

        $adjustment->load([
            'creator',
            'items.item',
        ]);

        $this->assertSame(
            StockAdjustmentStatus::Draft,
            $adjustment->status,
        );
        $this->assertTrue($adjustment->isDraft());
        $this->assertFalse($adjustment->isPosted());
        $this->assertTrue($adjustment->creator->is($user));
        $this->assertCount(1, $adjustment->items);
        $this->assertTrue(
            $adjustment->items->first()->is($adjustmentItem),
        );
        $this->assertTrue($adjustmentItem->item->is($item));
        $this->assertSame('10.00', $adjustmentItem->system_quantity);
        $this->assertSame('8.00', $adjustmentItem->physical_quantity);
        $this->assertSame('-2.00', $adjustmentItem->difference_quantity);
    }

    public function test_inventory_request_and_status_history_use_expected_contract(): void
    {
        $requester = User::factory()->create();
        $reviewer = User::factory()->create();
        $item = $this->createItem();

        $inventoryRequest = InventoryRequest::query()->create([
            'request_number' => 'REQ/2026/07/0001',
            'requested_by' => $requester->id,
            'employee_number_snapshot' => $requester->employee_number,
            'requester_name_snapshot' => $requester->name,
            'work_unit_snapshot' => $requester->work_unit,
            'request_date' => now(),
            'purpose' => 'Kebutuhan operasional pengujian.',
            'notes' => null,
            'status' => InventoryRequestStatus::UnderReview,
            'submitted_at' => now()->subMinutes(5),
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $requestItem = InventoryRequestItem::query()->create([
            'inventory_request_id' => $inventoryRequest->id,
            'item_id' => $item->id,
            'item_code_snapshot' => $item->item_code,
            'item_name_snapshot' => $item->name,
            'unit_snapshot' => $item->unit->symbol,
            'requested_quantity' => 5,
            'approved_quantity' => null,
            'reserved_quantity' => 0,
            'delivered_quantity' => null,
            'notes' => 'Kebutuhan pengujian.',
            'admin_notes' => null,
        ]);

        $history = InventoryRequestStatusHistory::query()->create([
            'inventory_request_id' => $inventoryRequest->id,
            'previous_status' => InventoryRequestStatus::Submitted,
            'new_status' => InventoryRequestStatus::UnderReview,
            'notes' => 'Masuk tahap peninjauan.',
            'changed_by' => $reviewer->id,
            'changed_at' => now(),
        ]);

        $inventoryRequest->load([
            'requester',
            'reviewer',
            'items.item',
            'statusHistories.changer',
        ]);

        $this->assertSame(
            InventoryRequestStatus::UnderReview,
            $inventoryRequest->status,
        );
        $this->assertTrue($inventoryRequest->requester->is($requester));
        $this->assertTrue($inventoryRequest->reviewer->is($reviewer));
        $this->assertCount(1, $inventoryRequest->items);
        $this->assertTrue(
            $inventoryRequest->items->first()->is($requestItem),
        );
        $this->assertSame('5.00', $requestItem->requested_quantity);
        $this->assertSame('0.00', $requestItem->reserved_quantity);

        $loadedHistory = $inventoryRequest->statusHistories->first();

        $this->assertTrue($loadedHistory->is($history));
        $this->assertSame(
            InventoryRequestStatus::Submitted,
            $loadedHistory->previous_status,
        );
        $this->assertSame(
            InventoryRequestStatus::UnderReview,
            $loadedHistory->new_status,
        );
        $this->assertTrue($loadedHistory->changer->is($reviewer));

        $this->assertModelCannotBeChanged(
            $history,
            ['notes' => 'Riwayat tidak boleh diubah.'],
        );
    }

    public function test_stock_movement_is_polymorphic_and_immutable(): void
    {
        $user = User::factory()->create();
        $item = $this->createItem();

        $receipt = InventoryReceipt::query()->create([
            'receipt_number' => 'STK-IN/2026/07/0002',
            'receipt_date' => now(),
            'source' => 'Pengadaan',
            'reference_number' => null,
            'notes' => null,
            'status' => InventoryReceiptStatus::Posted,
            'created_by' => $user->id,
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);

        $movement = StockMovement::query()->create([
            'movement_number' => 'MOV/2026/07/0001',
            'reference_number' => $receipt->receipt_number,
            'item_id' => $item->id,
            'movement_type' => StockMovementType::StockIn,
            'reference_type' => $receipt->getMorphClass(),
            'reference_id' => $receipt->id,
            'quantity_in' => 5,
            'quantity_out' => 0,
            'stock_before' => 0,
            'stock_after' => 5,
            'transaction_date' => now(),
            'description' => 'Barang masuk dari pengadaan.',
            'created_by' => $user->id,
        ]);

        $movement->load([
            'item',
            'reference',
            'creator',
        ]);

        $this->assertSame(
            StockMovementType::StockIn,
            $movement->movement_type,
        );
        $this->assertTrue($movement->isInbound());
        $this->assertFalse($movement->isOutbound());
        $this->assertSame('5.00', $movement->quantity_in);
        $this->assertSame('0.00', $movement->quantity_out);
        $this->assertSame('0.00', $movement->stock_before);
        $this->assertSame('5.00', $movement->stock_after);
        $this->assertTrue($movement->item->is($item));
        $this->assertTrue($movement->reference->is($receipt));
        $this->assertTrue($movement->creator->is($user));
        $this->assertNotNull($movement->created_at);

        $this->assertModelCannotBeChanged(
            $movement,
            ['description' => 'Ledger tidak boleh diubah.'],
        );
    }

    private function createItem(): Item
    {
        $category = ItemCategory::query()->create([
            'name' => 'Alat Tulis Kantor',
            'description' => 'Kategori pengujian transaksi persediaan.',
            'is_active' => true,
        ]);

        $unit = Unit::query()->create([
            'name' => 'Buah',
            'symbol' => 'buah',
            'is_active' => true,
        ]);

        $item = Item::query()->create([
            'item_code' => 'ATK-TEST-001',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Pulpen Pengujian',
            'description' => 'Barang pengujian transaksi persediaan.',
            'minimum_stock' => 5,
            'storage_location' => 'Gudang Utama',
            'is_active' => true,
        ]);

        return $item->load('unit');
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function assertModelCannotBeChanged(
        Model $model,
        array $changes,
    ): void {
        try {
            $model->update($changes);

            $this->fail('Model immutable masih dapat diubah.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'tidak boleh diubah',
                $exception->getMessage(),
            );
        }

        $model->refresh();

        foreach ($changes as $attribute => $value) {
            $this->assertNotEquals(
                $value,
                $model->getAttribute($attribute),
            );
        }

        try {
            $model->delete();

            $this->fail('Model immutable masih dapat dihapus.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'tidak boleh dihapus',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas($model->getTable(), [
            $model->getKeyName() => $model->getKey(),
        ]);
    }
}

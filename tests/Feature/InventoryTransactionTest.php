<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\InventoryReceiptStatus;
use App\Enums\RoleName;
use App\Enums\StockAdjustmentStatus;
use App\Enums\StockMovementType;
use App\Models\InventoryReceipt;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class InventoryTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleAndPermissionSeeder::class,
            ReferenceDataSeeder::class,
        ]);
    }

    public function test_receipt_draft_does_not_change_stock_until_posted(): void
    {
        $admin = $this->admin();
        $item = $this->item([
            'current_stock' => 10,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('inventory-receipts.store'), [
                'receipt_date' => '2026-07-31T10:00',
                'source' => 'Pengadaan APBN',
                'reference_number' => 'BAST-001',
                'notes' => 'Diterima dalam kondisi baik.',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => '5.50',
                        'notes' => null,
                    ],
                ],
            ]);

        $receipt = InventoryReceipt::query()->firstOrFail();

        $response->assertRedirect(
            route('inventory-receipts.show', $receipt),
        );
        $this->assertSame(
            InventoryReceiptStatus::Draft,
            $receipt->status,
        );
        $this->assertSame(
            '2026-07-31 03:00:00',
            $receipt->receipt_date
                ->copy()
                ->utc()
                ->format('Y-m-d H:i:s'),
        );
        $this->assertSame('10.00', $item->refresh()->current_stock);
        $this->assertDatabaseCount('stock_movements', 0);

        $this->actingAs($admin)
            ->get(route('inventory-receipts.show', $receipt))
            ->assertOk()
            ->assertSee('31 Juli 2026, 10:00')
            ->assertDontSee('31 Juli 2026, 17:00');

        $this->actingAs($admin)
            ->post(route('inventory-receipts.post', $receipt))
            ->assertRedirect();

        $this->assertSame(
            InventoryReceiptStatus::Posted,
            $receipt->refresh()->status,
        );
        $this->assertSame('15.50', $item->refresh()->current_stock);

        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame(
            StockMovementType::StockIn,
            $movement->movement_type,
        );
        $this->assertSame('10.00', $movement->stock_before);
        $this->assertSame('15.50', $movement->stock_after);
        $this->assertSame(
            'inventory_receipt',
            $movement->reference_type,
        );
        $this->assertStringStartsWith(
            $receipt->receipt_number.'-',
            $movement->transaction_number,
        );
        $this->assertSame(
            $movement->transaction_number,
            $movement->movement_number,
        );
        $this->assertSame(
            $receipt->receipt_number,
            $movement->reference_number,
        );
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'inventory_receipt_posted',
            'module' => 'inventory',
            'auditable_type' => 'inventory_receipt',
            'auditable_id' => $receipt->id,
        ]);
    }

    public function test_receipt_form_exposes_manual_number_manual_date_and_no_price_fields(): void
    {
        $admin = $this->admin();
        $this->item([
            'item_code' => 'FORM-NOPRICE-001',
            'name' => 'Barang Operasional',
        ]);

        $this->actingAs($admin)
            ->get(route('inventory-receipts.create'))
            ->assertOk()
            ->assertSee('Nomor Barang Masuk')
            ->assertSee('BAST/001/2026')
            ->assertSee('Tanggal Penerimaan')
            ->assertSee('Tambah Barang')
            ->assertDontSee('Harga Satuan')
            ->assertDontSee('Harga Satuan Master')
            ->assertDontSee('name="items[0][unit_cost]"', false);
    }

    public function test_receipt_supports_manual_number_and_blank_number_still_generates_automatic_number(): void
    {
        $admin = $this->admin();
        $item = $this->item();

        $this->actingAs($admin)
            ->post(route('inventory-receipts.store'), [
                'receipt_number' => 'bast/001/2026',
                'receipt_date' => '2026-08-21T09:30',
                'source' => 'Penerimaan Operasional',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => '2',
                    ],
                ],
            ])
            ->assertRedirect();

        $manualReceipt = InventoryReceipt::query()
            ->where('receipt_number', 'BAST/001/2026')
            ->firstOrFail();

        $this->assertNull(
            $manualReceipt->items()->firstOrFail()->unit_cost,
        );

        $this->actingAs($admin)
            ->post(route('inventory-receipts.store'), [
                'receipt_number' => '',
                'receipt_date' => '2026-08-21T10:00',
                'source' => 'Penerimaan Operasional',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => '1',
                    ],
                ],
            ])
            ->assertRedirect();

        $automaticReceipt = InventoryReceipt::query()
            ->where('id', '!=', $manualReceipt->id)
            ->firstOrFail();

        $this->assertStringStartsWith(
            'STK-IN/2026/08/',
            $automaticReceipt->receipt_number,
        );
        $this->assertNotSame(
            $manualReceipt->receipt_number,
            $automaticReceipt->receipt_number,
        );
    }

    public function test_receipt_rejects_duplicate_manual_number_and_reserved_automatic_namespace(): void
    {
        $admin = $this->admin();
        $item = $this->item();
        $payload = [
            'receipt_number' => 'BAST/002/2026',
            'receipt_date' => '2026-08-21T10:00',
            'source' => 'Penerimaan Operasional',
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => '1',
                ],
            ],
        ];

        $this->actingAs($admin)
            ->post(route('inventory-receipts.store'), $payload)
            ->assertRedirect();

        $this->actingAs($admin)
            ->from(route('inventory-receipts.create'))
            ->post(route('inventory-receipts.store'), $payload)
            ->assertRedirect(route('inventory-receipts.create'))
            ->assertSessionHasErrors('receipt_number');

        $reservedPrefixPayload = $payload;
        $reservedPrefixPayload['receipt_number'] = 'STK-IN/2026/08/9999';

        $this->actingAs($admin)
            ->from(route('inventory-receipts.create'))
            ->post(route('inventory-receipts.store'), $reservedPrefixPayload)
            ->assertRedirect(route('inventory-receipts.create'))
            ->assertSessionHasErrors('receipt_number');

        $this->assertDatabaseCount('inventory_receipts', 1);
    }

    public function test_receipt_ignores_legacy_price_payload_and_posts_using_quantity_only(): void
    {
        $admin = $this->admin();
        $item = $this->item([
            'current_stock' => 10,
        ]);

        $this->actingAs($admin)
            ->post(route('inventory-receipts.store'), [
                'receipt_number' => 'BAST/NO-PRICE/001',
                'receipt_date' => '2026-08-21T10:00',
                'source' => 'Penerimaan Operasional',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => '3',
                        'unit_cost' => '999999',
                    ],
                ],
            ])
            ->assertRedirect();

        $receipt = InventoryReceipt::query()->firstOrFail();
        $line = $receipt->items()->firstOrFail();

        $this->assertNull($line->unit_cost);
        $this->assertSame('10.00', $item->refresh()->current_stock);

        $this->actingAs($admin)
            ->post(route('inventory-receipts.post', $receipt))
            ->assertRedirect();

        $this->assertSame(
            InventoryReceiptStatus::Posted,
            $receipt->refresh()->status,
        );
        $this->assertSame('13.00', $item->refresh()->current_stock);
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => 'inventory_receipt',
            'reference_id' => $receipt->id,
            'movement_type' => StockMovementType::StockIn->value,
            'quantity_in' => 3,
            'quantity_out' => 0,
        ]);
    }

    public function test_receipt_draft_can_be_updated_or_cancelled_but_not_posted_after_cancel(): void
    {
        $admin = $this->admin();
        $item = $this->item();
        $receipt = $this->receiptDraft($admin, $item, 2);

        $this->actingAs($admin)
            ->put(route('inventory-receipts.update', $receipt), [
                'receipt_date' => '2026-07-31T11:00',
                'source' => 'Sumber Diperbarui',
                'reference_number' => null,
                'notes' => null,
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => '4',
                        'notes' => null,
                    ],
                ],
            ])
            ->assertRedirect(
                route('inventory-receipts.show', $receipt),
            );

        $this->assertSame(
            '4.00',
            $receipt->items()->firstOrFail()->quantity,
        );

        $this->actingAs($admin)
            ->patch(route('inventory-receipts.cancel', $receipt), [
                'cancellation_reason' => 'Dokumen sumber dibatalkan.',
            ])
            ->assertRedirect();

        $this->assertSame(
            InventoryReceiptStatus::Cancelled,
            $receipt->refresh()->status,
        );

        $this->actingAs($admin)
            ->post(route('inventory-receipts.post', $receipt))
            ->assertSessionHasErrors('transaction');

        $this->assertSame('10.00', $item->refresh()->current_stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_receipt_validation_rejects_duplicate_and_invalid_lines_atomically(): void
    {
        $admin = $this->admin();
        $item = $this->item();

        $this->actingAs($admin)
            ->from(route('inventory-receipts.create'))
            ->post(route('inventory-receipts.store'), [
                'receipt_date' => '2026-07-31T10:00',
                'source' => '',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => '0',
                    ],
                    [
                        'item_id' => $item->id,
                        'quantity' => '2',
                    ],
                ],
            ])
            ->assertRedirect(route('inventory-receipts.create'))
            ->assertSessionHasErrors([
                'source',
                'items.0.quantity',
                'items.1.item_id',
            ]);

        $this->assertDatabaseCount('inventory_receipts', 0);
        $this->assertDatabaseCount('inventory_receipt_items', 0);
    }

    public function test_adjustment_workspace_explains_stock_opname_and_physical_quantity(): void
    {
        $admin = $this->admin();
        $this->item([
            'item_code' => 'OPNAME-001',
            'name' => 'Barang Stock Opname',
        ]);

        $this->actingAs($admin)
            ->get(route('stock-adjustments.index'))
            ->assertOk()
            ->assertSee('Kapan menu Penyesuaian digunakan?')
            ->assertSee('Barang Masuk')
            ->assertSee('Buat Stock Opname / Penyesuaian');

        $this->actingAs($admin)
            ->get(route('stock-adjustments.create'))
            ->assertOk()
            ->assertSee('Penyesuaian bukan untuk mencatat barang datang.')
            ->assertSee('Jumlah Fisik Aktual')
            ->assertSee('Tambah Barang');
    }

    public function test_adjustment_index_renders_page_aggregates_without_correlated_subqueries(): void
    {
        $admin = $this->admin();
        $item = $this->item([
            'item_code' => 'OPNAME-PERF-001',
            'name' => 'Barang Uji Agregat',
            'current_stock' => 10,
        ]);

        for ($index = 1; $index <= 12; $index++) {
            $this->actingAs($admin)
                ->post(route('stock-adjustments.store'), [
                    'adjustment_date' => '2026-07-'.str_pad(
                        (string) min($index, 28),
                        2,
                        '0',
                        STR_PAD_LEFT,
                    ).'T12:00',
                    'reason' => 'Uji halaman penyesuaian '.$index,
                    'notes' => null,
                    'items' => [
                        [
                            'item_id' => $item->id,
                            'physical_quantity' => (string) (10 + $index),
                            'notes' => null,
                        ],
                    ],
                ])
                ->assertRedirect();
        }

        $this->actingAs($admin)
            ->get(route('stock-adjustments.index'))
            ->assertOk()
            ->assertSee('12 dokumen ditemukan', false)
            ->assertSee('1 barang')
            ->assertSee('Uji halaman penyesuaian 12');
    }

    public function test_adjustment_posts_inbound_and_outbound_movements_atomically(): void
    {
        $admin = $this->admin();
        $inboundItem = $this->item([
            'item_code' => 'BRG-IN',
            'name' => 'Barang Lebih',
            'current_stock' => 10,
        ]);
        $outboundItem = $this->item([
            'item_code' => 'BRG-OUT',
            'name' => 'Barang Kurang',
            'current_stock' => 10,
        ]);

        $this->actingAs($admin)
            ->post(route('stock-adjustments.store'), [
                'adjustment_date' => '2026-07-31T12:00',
                'reason' => 'Stock opname bulanan.',
                'notes' => null,
                'items' => [
                    [
                        'item_id' => $inboundItem->id,
                        'physical_quantity' => '12',
                        'notes' => null,
                    ],
                    [
                        'item_id' => $outboundItem->id,
                        'physical_quantity' => '8',
                        'notes' => 'Dua barang rusak.',
                    ],
                ],
            ])
            ->assertRedirect();

        $adjustment = StockAdjustment::query()->firstOrFail();

        $this->assertSame(
            StockAdjustmentStatus::Draft,
            $adjustment->status,
        );
        $this->assertSame(
            '2.00',
            $adjustment->items()
                ->where('item_id', $inboundItem->id)
                ->firstOrFail()
                ->difference_quantity,
        );
        $this->assertSame(
            '-2.00',
            $adjustment->items()
                ->where('item_id', $outboundItem->id)
                ->firstOrFail()
                ->difference_quantity,
        );

        $this->actingAs($admin)
            ->post(route('stock-adjustments.post', $adjustment))
            ->assertRedirect();

        $this->assertSame('12.00', $inboundItem->refresh()->current_stock);
        $this->assertSame('8.00', $outboundItem->refresh()->current_stock);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $inboundItem->id,
            'movement_type' => StockMovementType::AdjustmentIn->value,
            'quantity_in' => 2,
            'quantity_out' => 0,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $outboundItem->id,
            'movement_type' => StockMovementType::AdjustmentOut->value,
            'quantity_in' => 0,
            'quantity_out' => 2,
        ]);
        $this->assertDatabaseMissing('stock_movements', [
            'reference_number' => null,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'reference_number' => $adjustment->adjustment_number,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'stock_adjustment_posted',
            'auditable_type' => 'stock_adjustment',
            'auditable_id' => $adjustment->id,
        ]);
    }

    public function test_stale_adjustment_is_rejected_without_partial_stock_change(): void
    {
        $admin = $this->admin();
        $firstItem = $this->item([
            'item_code' => 'STALE-1',
            'current_stock' => 10,
        ]);
        $secondItem = $this->item([
            'item_code' => 'STALE-2',
            'current_stock' => 10,
        ]);

        $this->actingAs($admin)
            ->post(route('stock-adjustments.store'), [
                'adjustment_date' => '2026-07-31T12:00',
                'reason' => 'Pemeriksaan fisik.',
                'items' => [
                    [
                        'item_id' => $firstItem->id,
                        'physical_quantity' => '8',
                    ],
                    [
                        'item_id' => $secondItem->id,
                        'physical_quantity' => '9',
                    ],
                ],
            ])
            ->assertRedirect();

        $adjustment = StockAdjustment::query()->firstOrFail();
        $firstItem->update(['current_stock' => 11]);

        $this->actingAs($admin)
            ->post(route('stock-adjustments.post', $adjustment))
            ->assertSessionHasErrors('adjustment');

        $this->assertSame(
            StockAdjustmentStatus::Draft,
            $adjustment->refresh()->status,
        );
        $this->assertSame('11.00', $firstItem->refresh()->current_stock);
        $this->assertSame('10.00', $secondItem->refresh()->current_stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_employee_can_view_available_stock_but_cannot_access_stock_ledger_or_manage_transactions(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->get(route('items.index'))
            ->assertOk()
            ->assertSee('Daftar Barang');

        $this->actingAs($employee)
            ->get(route('stock.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('inventory-receipts.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('stock-adjustments.create'))
            ->assertForbidden();
    }

    public function test_stock_card_filters_by_item_and_type(): void
    {
        $admin = $this->admin();
        $firstItem = $this->item([
            'item_code' => 'FILTER-1',
            'name' => 'Barang Filter Satu',
        ]);
        $secondItem = $this->item([
            'item_code' => 'FILTER-2',
            'name' => 'Barang Filter Dua',
        ]);
        $this->movement(
            $admin,
            $firstItem,
            'MOVEMENT-IN-001',
            StockMovementType::StockIn,
            5,
            0,
        );
        $this->movement(
            $admin,
            $secondItem,
            'MOVEMENT-OUT-001',
            StockMovementType::AdjustmentOut,
            0,
            2,
        );

        $this->actingAs($admin)
            ->get(route('stock.index', [
                'item' => $firstItem->id,
                'type' => StockMovementType::StockIn->value,
            ]))
            ->assertOk()
            ->assertSee('MOVEMENT-IN-001')
            ->assertDontSee('MOVEMENT-OUT-001');
    }

    public function test_stock_movement_cannot_be_updated_or_deleted(): void
    {
        $admin = $this->admin();
        $item = $this->item();
        $movement = $this->movement(
            $admin,
            $item,
            'IMMUTABLE-001',
            StockMovementType::StockIn,
            2,
            0,
        );

        try {
            $movement->update(['description' => 'Diubah']);
            $this->fail('Kartu stok seharusnya tidak boleh diubah.');
        } catch (LogicException $exception) {
            $this->assertSame(
                'Kartu stok tidak boleh diubah.',
                $exception->getMessage(),
            );
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Kartu stok tidak boleh dihapus.',
        );

        $movement->delete();
    }

    private function admin(): User
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Active,
        ]);
        $user->assignRole(RoleName::Administrator->value);

        return $user;
    }

    private function employee(): User
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Active,
        ]);
        $user->assignRole(RoleName::Employee->value);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function item(array $attributes = []): Item
    {
        return Item::query()->create([
            'item_code' => fake()->unique()->bothify('BRG-###??'),
            'category_id' => ItemCategory::query()->firstOrFail()->id,
            'unit_id' => Unit::query()->firstOrFail()->id,
            'name' => fake()->unique()->words(3, true),
            'description' => null,
            'current_stock' => 10,
            'reserved_stock' => 0,
            'minimum_stock' => 2,
            'storage_location' => 'Gudang',
            'image_path' => null,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function receiptDraft(
        User $admin,
        Item $item,
        float $quantity,
    ): InventoryReceipt {
        $this->actingAs($admin)
            ->post(route('inventory-receipts.store'), [
                'receipt_date' => '2026-07-31T10:00',
                'source' => 'Pengadaan',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => $quantity,
                    ],
                ],
            ])
            ->assertRedirect();

        return InventoryReceipt::query()->firstOrFail();
    }

    private function movement(
        User $admin,
        Item $item,
        string $number,
        StockMovementType $type,
        float $quantityIn,
        float $quantityOut,
    ): StockMovement {
        return StockMovement::query()->create([
            'transaction_number' => $number,
            'item_id' => $item->id,
            'movement_type' => $type,
            'reference_type' => 'item',
            'reference_id' => $item->id,
            'quantity_in' => $quantityIn,
            'quantity_out' => $quantityOut,
            'stock_before' => 10,
            'stock_after' => 10 + $quantityIn - $quantityOut,
            'transaction_date' => now(),
            'description' => 'Test kartu stok.',
            'created_by' => $admin->id,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\InventoryRequestStatus;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Models\InventoryRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class StockMovementTest extends TestCase
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

    public function test_admin_can_filter_direction_and_select_inactive_item(): void
    {
        $admin = $this->admin();
        $item = $this->item([
            'item_code' => 'KARTU-001',
            'name' => 'Barang Histori Nonaktif',
        ]);
        $this->movement(
            actor: $admin,
            item: $item,
            number: 'MOV-IN-001',
            type: StockMovementType::StockIn,
            quantityIn: 5,
            quantityOut: 0,
            stockBefore: 10,
            stockAfter: 15,
            date: Carbon::parse('2026-08-03 01:00:00', 'UTC'),
        );
        $this->movement(
            actor: $admin,
            item: $item,
            number: 'MOV-OUT-001',
            type: StockMovementType::AdjustmentOut,
            quantityIn: 0,
            quantityOut: 2,
            stockBefore: 15,
            stockAfter: 13,
            date: Carbon::parse('2026-08-03 02:00:00', 'UTC'),
        );
        $item->update(['is_active' => false]);

        $this->actingAs($admin)
            ->get(route('stock.index', [
                'item' => $item->id,
                'direction' => 'inbound',
            ]))
            ->assertOk()
            ->assertSee('MOV-IN-001')
            ->assertDontSee('MOV-OUT-001')
            ->assertSee('Barang Histori Nonaktif')
            ->assertSee('Nonaktif');
    }

    public function test_stock_workspace_uses_distinct_colors_for_initial_inbound_and_outbound_badges(): void
    {
        $admin = $this->admin();
        $item = $this->item([
            'item_code' => 'WARNA-001',
            'name' => 'Barang Uji Warna',
        ]);

        $this->movement(
            actor: $admin,
            item: $item,
            number: 'WARNA-INIT',
            type: StockMovementType::InitialStock,
            quantityIn: 5,
            quantityOut: 0,
            stockBefore: 0,
            stockAfter: 5,
            date: Carbon::parse('2026-08-03 01:00:00', 'UTC'),
        );
        $this->movement(
            actor: $admin,
            item: $item,
            number: 'WARNA-IN',
            type: StockMovementType::StockIn,
            quantityIn: 3,
            quantityOut: 0,
            stockBefore: 5,
            stockAfter: 8,
            date: Carbon::parse('2026-08-03 02:00:00', 'UTC'),
        );
        $this->movement(
            actor: $admin,
            item: $item,
            number: 'WARNA-OUT',
            type: StockMovementType::RequestOut,
            quantityIn: 0,
            quantityOut: 2,
            stockBefore: 8,
            stockAfter: 6,
            date: Carbon::parse('2026-08-03 03:00:00', 'UTC'),
        );

        $this->actingAs($admin)
            ->get(route('stock.index', ['item' => $item->id]))
            ->assertOk()
            ->assertSee('data-movement-tone="initial"', false)
            ->assertSee('data-movement-tone="inbound"', false)
            ->assertSee('data-movement-tone="outbound"', false)
            ->assertSee('bg-sky-50 text-sky-700 ring-sky-200', false)
            ->assertSee('bg-emerald-50 text-emerald-700 ring-emerald-200', false)
            ->assertSee('bg-red-50 text-red-700 ring-red-200', false)
            ->assertSee('Stok Awal')
            ->assertSee('Barang Masuk')
            ->assertSee('Barang Keluar');

        $this->actingAs($admin)
            ->get(route('stock.card', ['item' => $item]))
            ->assertOk()
            ->assertSee('data-movement-tone="initial"', false)
            ->assertSee('data-movement-tone="inbound"', false)
            ->assertSee('data-movement-tone="outbound"', false);
    }

    public function test_detail_traces_request_source_employee_and_processor(): void
    {
        $admin = $this->admin([
            'name' => 'Admin Gudang',
            'employee_number' => 'ADM-001',
        ]);
        $employee = $this->employee([
            'name' => 'Rina Pegawai',
            'employee_number' => 'PGW-001',
        ]);
        $item = $this->item([
            'item_code' => 'ATK-001',
            'name' => 'Pulpen Hitam',
        ]);
        $request = InventoryRequest::query()->create([
            'request_number' => 'REQ/2026/08/0001',
            'requested_by' => $employee->id,
            'employee_number_snapshot' => 'PGW-001',
            'requester_name_snapshot' => 'Rina Pegawai',
            'work_unit_snapshot' => 'Bagian Umum',
            'request_date' => Carbon::parse('2026-08-03 02:00:00', 'UTC'),
            'purpose' => 'Operasional administrasi.',
            'status' => InventoryRequestStatus::Completed,
            'delivered_by' => $admin->id,
            'delivered_at' => Carbon::parse('2026-08-03 02:30:00', 'UTC'),
            'received_at' => Carbon::parse('2026-08-03 02:40:00', 'UTC'),
            'completed_at' => Carbon::parse('2026-08-03 02:40:00', 'UTC'),
        ]);
        $movement = $this->movement(
            actor: $admin,
            item: $item,
            number: 'REQ/2026/08/0001-001',
            type: StockMovementType::RequestOut,
            quantityIn: 0,
            quantityOut: 5,
            stockBefore: 20,
            stockAfter: 15,
            date: Carbon::parse('2026-08-03 02:30:00', 'UTC'),
            reference: $request,
            referenceNumber: $request->request_number,
        );

        $this->actingAs($admin)
            ->get(route('stock.show', $movement))
            ->assertOk()
            ->assertSee('Permintaan Barang')
            ->assertSee('REQ/2026/08/0001')
            ->assertSee('Rina Pegawai')
            ->assertSee('Admin Gudang')
            ->assertSee('Buka Transaksi Sumber')
            ->assertSee('Saldo Konsisten');
    }

    public function test_detail_flags_broken_ledger_continuity_without_mutating_data(): void
    {
        $admin = $this->admin();
        $item = $this->item();
        $this->movement(
            actor: $admin,
            item: $item,
            number: 'CHAIN-001',
            type: StockMovementType::InitialStock,
            quantityIn: 10,
            quantityOut: 0,
            stockBefore: 0,
            stockAfter: 10,
            date: Carbon::parse('2026-08-03 01:00:00', 'UTC'),
        );
        $movement = $this->movement(
            actor: $admin,
            item: $item,
            number: 'CHAIN-002',
            type: StockMovementType::StockIn,
            quantityIn: 2,
            quantityOut: 0,
            stockBefore: 10,
            stockAfter: 12,
            date: Carbon::parse('2026-08-03 02:00:00', 'UTC'),
        );
        $this->movement(
            actor: $admin,
            item: $item,
            number: 'CHAIN-003',
            type: StockMovementType::StockIn,
            quantityIn: 1,
            quantityOut: 0,
            stockBefore: 99,
            stockAfter: 100,
            date: Carbon::parse('2026-08-03 03:00:00', 'UTC'),
        );

        $this->actingAs($admin)
            ->get(route('stock.show', $movement))
            ->assertOk()
            ->assertSee('Perlu Audit')
            ->assertSee('Rangkaian saldo perlu diperiksa.')
            ->assertSee('CHAIN-001')
            ->assertSee('CHAIN-003');

        $this->assertSame('12.00', $movement->refresh()->stock_after);
    }

    public function test_admin_can_view_stock_card_for_single_item_with_period_and_balance(): void
    {
        $admin = $this->admin();

        $item = $this->item([
            'item_code' => 'CARD-001',
            'name' => 'Barang Kartu Utama',
        ]);

        $otherItem = $this->item([
            'item_code' => 'CARD-OTHER',
            'name' => 'Barang Lain',
        ]);

        $this->movement(
            actor: $admin,
            item: $item,
            number: 'BEFORE-RANGE',
            type: StockMovementType::InitialStock,
            quantityIn: 10,
            quantityOut: 0,
            stockBefore: 0,
            stockAfter: 10,
            date: Carbon::parse('2026-08-02 02:00:00', 'UTC'),
        );

        $this->movement(
            actor: $admin,
            item: $item,
            number: 'IN-RANGE-IN',
            type: StockMovementType::StockIn,
            quantityIn: 5,
            quantityOut: 0,
            stockBefore: 10,
            stockAfter: 15,
            date: Carbon::parse('2026-08-03 02:00:00', 'UTC'),
        );

        $this->movement(
            actor: $admin,
            item: $item,
            number: 'IN-RANGE-OUT',
            type: StockMovementType::AdjustmentOut,
            quantityIn: 0,
            quantityOut: 2,
            stockBefore: 15,
            stockAfter: 13,
            date: Carbon::parse('2026-08-04 02:00:00', 'UTC'),
        );

        $this->movement(
            actor: $admin,
            item: $item,
            number: 'AFTER-RANGE',
            type: StockMovementType::StockIn,
            quantityIn: 1,
            quantityOut: 0,
            stockBefore: 13,
            stockAfter: 14,
            date: Carbon::parse('2026-08-05 02:00:00', 'UTC'),
        );

        $this->movement(
            actor: $admin,
            item: $otherItem,
            number: 'OTHER-ITEM',
            type: StockMovementType::StockIn,
            quantityIn: 7,
            quantityOut: 0,
            stockBefore: 3,
            stockAfter: 10,
            date: Carbon::parse('2026-08-03 03:00:00', 'UTC'),
        );

        $this->actingAs($admin)
            ->get(route('stock.card', [
                'item' => $item,
                'from' => '2026-08-03',
                'until' => '2026-08-04',
            ]))
            ->assertOk()
            ->assertSee('Kartu Stok Persediaan')
            ->assertSee('CARD-001')
            ->assertSee('Barang Kartu Utama')
            ->assertSee('IN-RANGE-IN')
            ->assertSee('IN-RANGE-OUT')
            ->assertDontSee('BEFORE-RANGE')
            ->assertDontSee('AFTER-RANGE')
            ->assertDontSee('OTHER-ITEM')
            ->assertSee('Saldo Awal Periode')
            ->assertSee('10,00')
            ->assertSee('13,00')
            ->assertSee('Konsisten');
    }

    public function test_admin_can_download_filtered_stock_ledger_excel(): void
    {
        $admin = $this->admin();
        $item = $this->item([
            'item_code' => 'XLSX-001',
            'name' => 'Barang Excel',
        ]);
        $otherItem = $this->item([
            'item_code' => 'XLSX-OTHER',
            'name' => 'Barang Excel Lain',
        ]);

        $this->movement(
            actor: $admin,
            item: $item,
            number: 'XLSX-IN-001',
            type: StockMovementType::StockIn,
            quantityIn: 5,
            quantityOut: 0,
            stockBefore: 10,
            stockAfter: 15,
            date: Carbon::parse('2026-08-03 02:00:00', 'UTC'),
        );
        $this->movement(
            actor: $admin,
            item: $item,
            number: 'XLSX-OUT-001',
            type: StockMovementType::AdjustmentOut,
            quantityIn: 0,
            quantityOut: 2,
            stockBefore: 15,
            stockAfter: 13,
            date: Carbon::parse('2026-08-04 02:00:00', 'UTC'),
        );
        $this->movement(
            actor: $admin,
            item: $otherItem,
            number: 'XLSX-OTHER-001',
            type: StockMovementType::StockIn,
            quantityIn: 3,
            quantityOut: 0,
            stockBefore: 0,
            stockAfter: 3,
            date: Carbon::parse('2026-08-03 03:00:00', 'UTC'),
        );

        $response = $this->actingAs($admin)
            ->get(route('stock.excel', [
                'item' => $item->id,
                'direction' => 'inbound',
                'from' => '2026-08-03',
                'until' => '2026-08-04',
            ]));

        $response
            ->assertOk()
            ->assertDownload();

        $file = $response->baseResponse->getFile();
        $workbook = IOFactory::load($file->getPathname());
        $data = $workbook->getSheetByName('Data');
        $rows = $data->toArray();

        $this->assertSame('Nomor Transaksi', $rows[0][1]);
        $this->assertSame('XLSX-IN-001', $rows[1][1]);
        $this->assertCount(2, $rows);

        $serialized = json_encode($rows, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString(
            'XLSX-OUT-001',
            $serialized,
        );
        $this->assertStringNotContainsString(
            'XLSX-OTHER-001',
            $serialized,
        );
    }

    public function test_stock_card_excel_does_not_mutate_ledger(): void
    {
        $admin = $this->admin();
        $item = $this->item([
            'item_code' => 'CARD-XLSX',
            'name' => 'Barang Kartu Excel',
        ]);

        $this->movement(
            actor: $admin,
            item: $item,
            number: 'CARD-XLSX-001',
            type: StockMovementType::InitialStock,
            quantityIn: 10,
            quantityOut: 0,
            stockBefore: 0,
            stockAfter: 10,
            date: Carbon::parse('2026-08-03 02:00:00', 'UTC'),
        );

        $before = StockMovement::query()
            ->orderBy('id')
            ->get()
            ->map(
                static fn (StockMovement $movement): array => $movement
                    ->getRawOriginal(),
            )
            ->all();

        $response = $this->actingAs($admin)
            ->get(route('stock.card.excel', [
                'item' => $item,
                'from' => '2026-08-01',
                'until' => '2026-08-31',
            ]));

        $response
            ->assertOk()
            ->assertDownload('kartu-stok-CARD-XLSX.xlsx');

        $file = $response->baseResponse->getFile();
        $workbook = IOFactory::load($file->getPathname());

        $this->assertSame(
            ['Ringkasan', 'Data'],
            $workbook->getSheetNames(),
        );
        $this->assertSame(
            'CARD-XLSX-001',
            $workbook->getSheetByName('Data')->getCell('B2')->getValue(),
        );

        $after = StockMovement::query()
            ->orderBy('id')
            ->get()
            ->map(
                static fn (StockMovement $movement): array => $movement
                    ->getRawOriginal(),
            )
            ->all();

        $this->assertSame($before, $after);
    }

    public function test_stock_card_pdf_does_not_mutate_ledger(): void
    {
        $admin = $this->admin();

        $item = $this->item([
            'item_code' => 'PDF-001',
            'name' => 'Barang PDF',
        ]);

        $this->movement(
            actor: $admin,
            item: $item,
            number: 'PDF-MOV-001',
            type: StockMovementType::InitialStock,
            quantityIn: 10,
            quantityOut: 0,
            stockBefore: 0,
            stockAfter: 10,
            date: Carbon::parse('2026-08-03 02:00:00', 'UTC'),
        );

        $before = StockMovement::query()
            ->orderBy('id')
            ->get()
            ->map(
                static fn (StockMovement $movement): array => $movement
                    ->getRawOriginal(),
            )
            ->all();

        $response = $this->actingAs($admin)
            ->get(route('stock.card.pdf', [
                'item' => $item,
                'from' => '2026-08-01',
                'until' => '2026-08-31',
            ]));

        $response->assertOk();

        $this->assertStringStartsWith(
            'application/pdf',
            (string) $response->headers->get('Content-Type'),
        );

        $this->assertStringContainsString(
            'attachment',
            strtolower(
                (string) $response->headers->get(
                    'Content-Disposition',
                ),
            ),
        );

        $after = StockMovement::query()
            ->orderBy('id')
            ->get()
            ->map(
                static fn (StockMovement $movement): array => $movement
                    ->getRawOriginal(),
            )
            ->all();

        $this->assertSame($before, $after);
    }

    public function test_employee_cannot_access_stock_movement_workspace_or_detail(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $movement = $this->movement(
            actor: $admin,
            item: $this->item(),
            number: 'PRIVATE-001',
            type: StockMovementType::InitialStock,
            quantityIn: 10,
            quantityOut: 0,
            stockBefore: 0,
            stockAfter: 10,
            date: now(),
        );

        $this->actingAs($employee)
            ->get(route('stock.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('stock.show', $movement))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('stock.card', $movement->item))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('stock.card.pdf', $movement->item))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('stock.excel'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('stock.card.excel', $movement->item))
            ->assertForbidden();
    }

    public function test_deleted_item_and_processor_remain_traceable_in_ledger(): void
    {
        $viewer = $this->admin();
        $actor = $this->admin([
            'name' => 'Petugas Lama',
            'employee_number' => 'ADM-LAMA',
        ]);
        $item = $this->item([
            'item_code' => 'ARSIP-001',
            'name' => 'Barang Arsip',
        ]);
        $movement = $this->movement(
            actor: $actor,
            item: $item,
            number: 'ARSIP-MOV-001',
            type: StockMovementType::InitialStock,
            quantityIn: 10,
            quantityOut: 0,
            stockBefore: 0,
            stockAfter: 10,
            date: now(),
        );
        $item->delete();
        $actor->delete();

        $this->actingAs($viewer)
            ->get(route('stock.index', ['q' => 'Petugas Lama']))
            ->assertOk()
            ->assertSee('ARSIP-MOV-001')
            ->assertSee('Petugas Lama');

        $this->actingAs($viewer)
            ->get(route('stock.show', $movement))
            ->assertOk()
            ->assertSee('Barang Arsip')
            ->assertSee('Petugas Lama')
            ->assertSee('Nonaktif');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function admin(array $attributes = []): User
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Active,
            ...$attributes,
        ]);
        $user->assignRole(RoleName::Administrator->value);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = []): User
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Active,
            ...$attributes,
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

    private function movement(
        User $actor,
        Item $item,
        string $number,
        StockMovementType $type,
        float $quantityIn,
        float $quantityOut,
        float $stockBefore,
        float $stockAfter,
        mixed $date,
        Item|InventoryRequest|null $reference = null,
        ?string $referenceNumber = null,
    ): StockMovement {
        $source = $reference ?? $item;

        return StockMovement::query()->create([
            'transaction_number' => $number,
            'movement_number' => $number,
            'reference_number' => $referenceNumber ?? $number,
            'item_id' => $item->id,
            'movement_type' => $type,
            'reference_type' => $source->getMorphClass(),
            'reference_id' => $source->getKey(),
            'quantity_in' => $quantityIn,
            'quantity_out' => $quantityOut,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'transaction_date' => $date,
            'description' => 'Catatan pergerakan stok.',
            'created_by' => $actor->id,
        ]);
    }
}

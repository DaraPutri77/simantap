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

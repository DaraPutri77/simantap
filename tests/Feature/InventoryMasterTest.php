<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMasterTest extends TestCase
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

    public function test_admin_and_employee_can_view_allowed_inventory_pages(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $activeItem = $this->item(['name' => 'Kertas Aktif']);
        $inactiveItem = $this->item([
            'name' => 'Kertas Nonaktif',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('items.index'))
            ->assertOk()
            ->assertSee($activeItem->name)
            ->assertSee($inactiveItem->name)
            ->assertSee('Tambah Barang');

        $this->actingAs($employee)
            ->get(route('items.index'))
            ->assertOk()
            ->assertSee($activeItem->name)
            ->assertDontSee($inactiveItem->name)
            ->assertDontSee('Tambah Barang');

        $this->actingAs($employee)
            ->get(route('stock.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('item-categories.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_item_with_immutable_initial_stock_entry(): void
    {
        $admin = $this->admin();
        $category = ItemCategory::query()->firstOrFail();
        $unit = Unit::query()->firstOrFail();

        $response = $this->actingAs($admin)
            ->post(route('items.store'), [
                'item_code' => 'atk-100',
                'name' => 'Pulpen Biru',
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'description' => 'Pulpen untuk kegiatan kantor.',
                'initial_stock' => '12.50',
                'minimum_stock' => '5',
                'storage_location' => 'Gudang A',
                'is_active' => '1',
            ]);

        $item = Item::query()
            ->where('item_code', 'ATK-100')
            ->firstOrFail();

        $response->assertRedirect(route('items.show', $item));
        $this->assertSame('12.50', $item->current_stock);
        $this->assertSame('0.00', $item->reserved_stock);

        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame(
            StockMovementType::InitialStock,
            $movement->movement_type,
        );
        $this->assertSame('0.00', $movement->stock_before);
        $this->assertSame('12.50', $movement->stock_after);
        $this->assertSame('item', $movement->reference_type);
        $this->assertSame($item->id, $movement->reference_id);
        $this->assertStringStartsWith(
            'STK-INIT/',
            $movement->transaction_number,
        );
        $this->assertSame(
            $movement->transaction_number,
            $movement->movement_number,
        );
        $this->assertSame(
            $item->item_code,
            $movement->reference_number,
        );
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'item_created',
            'module' => 'inventory',
            'auditable_type' => 'item',
            'auditable_id' => $item->id,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_item_update_cannot_directly_change_stock_and_status_has_own_action(): void
    {
        $admin = $this->admin();
        $item = $this->item([
            'current_stock' => 20,
            'minimum_stock' => 3,
        ]);

        $this->actingAs($admin)
            ->put(route('items.update', $item), [
                'item_code' => 'NEW-CODE',
                'name' => 'Nama Baru',
                'category_id' => $item->category_id,
                'unit_id' => $item->unit_id,
                'minimum_stock' => '6',
                'storage_location' => 'Rak B',
                'description' => null,
                'is_active' => '1',
                'current_stock' => '999',
                'reserved_stock' => '999',
            ])
            ->assertRedirect(route('items.show', $item));

        $item->refresh();

        $this->assertSame('20.00', $item->current_stock);
        $this->assertSame('0.00', $item->reserved_stock);
        $this->assertSame('6.00', $item->minimum_stock);
        $this->assertSame('Nama Baru', $item->name);

        $this->actingAs($admin)
            ->patch(route('items.deactivate', $item))
            ->assertRedirect();
        $this->assertFalse($item->refresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('items.activate', $item))
            ->assertRedirect();
        $this->assertTrue($item->refresh()->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'item_deactivated',
            'auditable_id' => $item->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'item_activated',
            'auditable_id' => $item->id,
        ]);
    }

    public function test_item_validation_rejects_duplicate_code_and_invalid_master(): void
    {
        $admin = $this->admin();
        $item = $this->item(['item_code' => 'ATK-001']);

        $this->actingAs($admin)
            ->from(route('items.create'))
            ->post(route('items.store'), [
                'item_code' => mb_strtolower($item->item_code),
                'name' => '',
                'category_id' => 999999,
                'unit_id' => 999999,
                'initial_stock' => '-1',
                'minimum_stock' => '-1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('items.create'))
            ->assertSessionHasErrors([
                'item_code',
                'name',
                'category_id',
                'unit_id',
                'initial_stock',
                'minimum_stock',
            ]);

        $this->assertSame(1, Item::query()->count());
    }

    public function test_admin_can_manage_category_and_unit_with_audit_log(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('item-categories.store'), [
                'name' => 'Materai',
                'description' => 'Barang persuratan.',
                'is_active' => '1',
            ])
            ->assertRedirect(route('item-categories.index'));

        $category = ItemCategory::query()
            ->where('name', 'Materai')
            ->firstOrFail();

        $this->actingAs($admin)
            ->put(route('item-categories.update', $category), [
                'name' => 'Materai dan Perangko',
                'description' => 'Persuratan resmi.',
                'is_active' => '0',
            ])
            ->assertRedirect(route('item-categories.index'));

        $this->actingAs($admin)
            ->post(route('units.store'), [
                'name' => 'Dus',
                'symbol' => 'DUS',
                'is_active' => '1',
            ])
            ->assertRedirect(route('units.index'));

        $unit = Unit::query()->where('symbol', 'dus')->firstOrFail();

        $this->assertFalse($category->refresh()->is_active);
        $this->assertTrue($unit->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'item_category_created',
            'auditable_type' => 'item_category',
            'auditable_id' => $category->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'unit_created',
            'auditable_type' => 'unit',
            'auditable_id' => $unit->id,
        ]);
    }

    public function test_item_master_does_not_expose_commercial_price_fields(): void
    {
        $admin = $this->admin();
        $item = $this->item([
            'name' => 'Barang Operasional Tanpa Harga',
        ]);

        $this->actingAs($admin)
            ->get(route('items.index'))
            ->assertOk()
            ->assertDontSee('Harga Master')
            ->assertDontSee('Harga Satuan Master');

        $this->actingAs($admin)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertDontSee('Harga satuan master')
            ->assertDontSee('Harga master belum diisi');

        $this->actingAs($admin)
            ->get(route('items.edit', $item))
            ->assertOk()
            ->assertDontSee('name="unit_price"', false)
            ->assertDontSee('Harga Satuan Master');
    }

    public function test_item_edit_keeps_current_inactive_category_and_unit_selectable(): void
    {
        $admin = $this->admin();
        $category = ItemCategory::query()->create([
            'name' => 'Kategori Arsip Nonaktif',
            'description' => 'Dipertahankan untuk barang lama.',
            'is_active' => false,
        ]);
        $unit = Unit::query()->create([
            'name' => 'Bundel Nonaktif',
            'symbol' => 'BNDL',
            'is_active' => false,
        ]);
        $item = $this->item([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Barang Dengan Master Nonaktif',
        ]);

        $this->actingAs($admin)
            ->get(route('items.edit', $item))
            ->assertOk()
            ->assertSee('Kategori Arsip Nonaktif')
            ->assertSee('Bundel Nonaktif')
            ->assertSee('BNDL')
            ->assertSee('(nonaktif)');
    }

    public function test_item_filters_use_active_category_and_unit_master_in_alphabetical_order(): void
    {
        $admin = $this->admin();

        $categoryAlpha = ItemCategory::query()->create([
            'name' => 'AAA Kategori Filter',
            'description' => 'Kategori aktif untuk pengujian filter.',
            'is_active' => true,
        ]);
        $categoryZulu = ItemCategory::query()->create([
            'name' => 'ZZZ Kategori Filter',
            'description' => 'Kategori aktif untuk pengujian filter.',
            'is_active' => true,
        ]);
        ItemCategory::query()->create([
            'name' => 'MMM Kategori Filter Nonaktif',
            'description' => 'Tidak boleh muncul pada dropdown filter.',
            'is_active' => false,
        ]);

        $unitAlpha = Unit::query()->create([
            'name' => 'AAA Satuan Filter',
            'symbol' => 'AAF',
            'is_active' => true,
        ]);
        $unitZulu = Unit::query()->create([
            'name' => 'ZZZ Satuan Filter',
            'symbol' => 'ZZF',
            'is_active' => true,
        ]);
        Unit::query()->create([
            'name' => 'MMM Satuan Filter Nonaktif',
            'symbol' => 'MMF',
            'is_active' => false,
        ]);

        $matched = $this->item([
            'name' => 'Barang Cocok Filter',
            'category_id' => $categoryAlpha->id,
            'unit_id' => $unitAlpha->id,
        ]);
        $other = $this->item([
            'name' => 'Barang Lain Filter',
            'category_id' => $categoryZulu->id,
            'unit_id' => $unitZulu->id,
        ]);

        $this->actingAs($admin)
            ->get(route('items.index'))
            ->assertOk()
            ->assertSee('Semua kategori')
            ->assertSee('Semua satuan')
            ->assertSeeInOrder([
                'AAA Kategori Filter',
                'ZZZ Kategori Filter',
            ])
            ->assertSeeInOrder([
                'AAA Satuan Filter',
                'ZZZ Satuan Filter',
            ])
            ->assertDontSee('MMM Kategori Filter Nonaktif')
            ->assertDontSee('MMM Satuan Filter Nonaktif');

        $this->actingAs($admin)
            ->get(route('items.index', [
                'category' => $categoryAlpha->id,
                'unit' => $unitAlpha->id,
            ]))
            ->assertOk()
            ->assertSee($matched->name)
            ->assertDontSee($other->name);
    }

    public function test_low_stock_filter_uses_available_stock_not_physical_stock(): void
    {
        $admin = $this->admin();
        $lowItem = $this->item([
            'name' => 'Barang Hampir Habis',
            'current_stock' => 10,
            'reserved_stock' => 6,
            'minimum_stock' => 5,
        ]);
        $availableItem = $this->item([
            'name' => 'Barang Aman',
            'current_stock' => 10,
            'reserved_stock' => 1,
            'minimum_stock' => 5,
        ]);

        $this->actingAs($admin)
            ->get(route('items.index', ['stock' => 'low']))
            ->assertOk()
            ->assertSee($lowItem->name)
            ->assertDontSee($availableItem->name);
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
}

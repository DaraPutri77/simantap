<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemPricePersistenceTest extends TestCase
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

    public function test_admin_can_create_update_and_view_item_price(): void
    {
        $admin = User::factory()->create([
            'status' => AccountStatus::Active,
        ]);
        $admin->assignRole(RoleName::Administrator->value);
        $category = ItemCategory::query()->firstOrFail();
        $unit = Unit::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('items.store'), [
                'item_code' => 'ATK-HARGA-01',
                'name' => 'Barang Uji Harga',
                'harga' => '50000',
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'description' => null,
                'initial_stock' => '10',
                'minimum_stock' => '2',
                'storage_location' => 'Gudang A',
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $item = Item::query()
            ->where('item_code', 'ATK-HARGA-01')
            ->firstOrFail();

        $this->assertSame('50000.00', $item->harga);

        $this->actingAs($admin)
            ->get(route('items.index'))
            ->assertOk()
            ->assertSee('Rp 50.000');

        $this->actingAs($admin)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Harga')
            ->assertSee('Rp 50.000');

        $this->actingAs($admin)
            ->put(route('items.update', $item), [
                'item_code' => $item->item_code,
                'name' => $item->name,
                'harga' => '52500',
                'category_id' => $item->category_id,
                'unit_id' => $item->unit_id,
                'description' => $item->description,
                'minimum_stock' => $item->minimum_stock,
                'storage_location' => $item->storage_location,
                'remove_image' => '0',
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('52500.00', $item->refresh()->harga);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'item_updated',
            'auditable_type' => 'item',
            'auditable_id' => $item->id,
            'actor_id' => $admin->id,
        ]);
    }
}
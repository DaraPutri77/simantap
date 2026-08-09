<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Enums\VehicleStatus;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QrCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_detail_pages_render_stable_authenticated_qr_targets(): void
    {
        $admin = $this->admin();
        $item = $this->item();
        $vehicle = $this->vehicle();

        $this->actingAs($admin)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('QR Identitas')
            ->assertSee(route('items.show', $item))
            ->assertSee(route('qr-codes.item.svg', $item));

        $this->actingAs($admin)
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('QR Identitas')
            ->assertSee(route('vehicles.show', $vehicle))
            ->assertSee(route('qr-codes.vehicle.label', $vehicle));

        $this->assertStringContainsString(
            $item->public_id,
            route('items.show', $item),
        );
        $this->assertStringContainsString(
            $vehicle->public_id,
            route('vehicles.show', $vehicle),
        );
    }

    public function test_administrator_can_download_item_svg_and_vehicle_label_pdf_with_audit(): void
    {
        $admin = $this->admin();
        $item = $this->item();
        $vehicle = $this->vehicle();

        $this->actingAs($admin)
            ->get(route('qr-codes.item.svg', $item))
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml; charset=UTF-8')
            ->assertHeader(
                'content-disposition',
                'attachment; filename="QR-BARANG-'.$item->item_code.'.svg"',
            )
            ->assertSee('<svg', false);

        $this->actingAs($admin)
            ->get(route('qr-codes.vehicle.label', $vehicle))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'auditable_type' => 'item',
            'auditable_id' => $item->id,
            'event' => 'qr_code_downloaded',
            'module' => 'qr_code',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'auditable_type' => 'vehicle',
            'auditable_id' => $vehicle->id,
            'event' => 'qr_label_downloaded',
            'module' => 'qr_code',
        ]);
    }

    public function test_employee_can_scan_active_master_but_cannot_download_labels(): void
    {
        $employee = $this->employee();
        $item = $this->item();
        $vehicle = $this->vehicle();

        $this->actingAs($employee)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('QR Identitas')
            ->assertDontSee('Unduh SVG');

        $this->actingAs($employee)
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('QR Identitas')
            ->assertDontSee('Cetak Label PDF');

        $this->actingAs($employee)
            ->get(route('qr-codes.item.svg', $item))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('qr-codes.vehicle.label', $vehicle))
            ->assertForbidden();
    }

    public function test_qr_targets_do_not_expose_master_data_to_guests(): void
    {
        $item = $this->item();
        $vehicle = $this->vehicle();

        $this->get(route('items.show', $item))
            ->assertRedirect(route('login'));

        $this->get(route('vehicles.show', $vehicle))
            ->assertRedirect(route('login'));
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

    private function item(): Item
    {
        $category = ItemCategory::query()->create([
            'name' => 'Kategori QR '.Str::random(6),
            'is_active' => true,
        ]);
        $unit = Unit::query()->create([
            'name' => 'Unit QR '.Str::random(6),
            'symbol' => 'q'.Str::lower(Str::random(3)),
            'is_active' => true,
        ]);

        return Item::query()->create([
            'item_code' => 'BRG-QR-001',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Kertas QR',
            'current_stock' => 10,
            'reserved_stock' => 0,
            'minimum_stock' => 2,
            'storage_location' => 'Gudang A',
            'is_active' => true,
        ]);
    }

    private function vehicle(): Vehicle
    {
        return Vehicle::query()->create([
            'vehicle_code' => 'KND-QR-001',
            'license_plate' => 'B 1234 QR',
            'brand' => 'Toyota',
            'model' => 'Avanza',
            'year' => 2025,
            'color' => 'Hitam',
            'chassis_number' => 'MH1QR000000000001',
            'engine_number' => 'ENGQR0000001',
            'current_odometer' => 1000,
            'status' => VehicleStatus::Available,
            'registration_expiry_date' => '2027-08-09',
            'storage_location' => 'Garasi A',
            'responsible_person' => 'Pengelola Barang',
            'is_active' => true,
        ]);
    }
}

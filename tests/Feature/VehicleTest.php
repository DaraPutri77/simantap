<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Enums\VehicleStatus;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_create_vehicle_with_document_image_and_audit(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $payload = $this->validPayload([
            'vehicle_code' => ' knd-001 ',
            'license_plate' => ' s 1234 wi ',
            'chassis_number' => ' mh1kf1111pk000001 ',
            'engine_number' => ' kf11e1000001 ',
            'image' => UploadedFile::fake()->image(
                'motor.png',
                900,
                600,
            ),
        ]);

        $response = $this->actingAs($admin)
            ->post(route('vehicles.store'), $payload);

        $vehicle = Vehicle::query()->firstOrFail();

        $response->assertRedirect(route('vehicles.show', $vehicle));
        $this->assertSame('KND-001', $vehicle->vehicle_code);
        $this->assertTrue(Str::isUuid($vehicle->public_id));
        $this->assertSame('S 1234 WI', $vehicle->license_plate);
        $this->assertSame('MH1KF1111PK000001', $vehicle->chassis_number);
        $this->assertSame('KF11E1000001', $vehicle->engine_number);
        $this->assertSame('Garasi Kantor, Slot A-01', $vehicle->storage_location);
        $this->assertSame('Pengelola Barang', $vehicle->responsible_person);
        $this->assertSame(VehicleStatus::Available, $vehicle->status);
        $this->assertTrue($vehicle->is_active);
        $this->assertNotNull($vehicle->image_path);
        Storage::disk('public')->assertExists($vehicle->image_path);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_created',
            'module' => 'vehicle',
            'auditable_type' => 'vehicle',
            'auditable_id' => $vehicle->id,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_odometer_cannot_move_backward_and_image_can_be_removed(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $oldImage = UploadedFile::fake()
            ->image('old-vehicle.jpg')
            ->store('vehicles', 'public');
        $vehicle = $this->vehicle([
            'current_odometer' => 1250.5,
            'image_path' => $oldImage,
        ]);

        $this->actingAs($admin)
            ->from(route('vehicles.edit', $vehicle))
            ->put(route('vehicles.update', $vehicle), $this->validPayload([
                'vehicle_code' => $vehicle->vehicle_code,
                'license_plate' => $vehicle->license_plate,
                'chassis_number' => $vehicle->chassis_number,
                'engine_number' => $vehicle->engine_number,
                'current_odometer' => '1200.0',
                'remove_image' => '0',
            ]))
            ->assertRedirect(route('vehicles.edit', $vehicle))
            ->assertSessionHasErrors('current_odometer');

        $this->assertSame(
            '1250.5',
            $vehicle->refresh()->current_odometer,
        );
        Storage::disk('public')->assertExists($oldImage);

        $this->actingAs($admin)
            ->put(route('vehicles.update', $vehicle), $this->validPayload([
                'vehicle_code' => $vehicle->vehicle_code,
                'license_plate' => $vehicle->license_plate,
                'chassis_number' => $vehicle->chassis_number,
                'engine_number' => $vehicle->engine_number,
                'current_odometer' => '1300.0',
                'remove_image' => '1',
            ]))
            ->assertRedirect(route('vehicles.show', $vehicle));

        $vehicle->refresh();
        $this->assertSame('1300.0', $vehicle->current_odometer);
        $this->assertNull($vehicle->image_path);
        Storage::disk('public')->assertMissing($oldImage);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_updated',
            'auditable_type' => 'vehicle',
            'auditable_id' => $vehicle->id,
        ]);
    }

    public function test_vehicle_identifiers_must_remain_unique(): void
    {
        $admin = $this->admin();
        $existing = $this->vehicle([
            'vehicle_code' => 'KND-UNIK-01',
            'license_plate' => 'S 1555 AA',
            'chassis_number' => 'MH1UNIK0000000001',
            'engine_number' => 'ENGUNIK000001',
        ]);

        $this->actingAs($admin)
            ->from(route('vehicles.create'))
            ->post(route('vehicles.store'), $this->validPayload([
                'vehicle_code' => $existing->vehicle_code,
                'license_plate' => $existing->license_plate,
                'chassis_number' => $existing->chassis_number,
                'engine_number' => $existing->engine_number,
            ]))
            ->assertRedirect(route('vehicles.create'))
            ->assertSessionHasErrors([
                'vehicle_code',
                'license_plate',
                'chassis_number',
                'engine_number',
            ]);

        $this->assertDatabaseCount('vehicles', 1);
    }

    public function test_admin_can_filter_vehicle_status_registration_and_search(): void
    {
        Carbon::setTestNow('2026-08-03 02:00:00 UTC');
        $admin = $this->admin();
        $matching = $this->vehicle([
            'vehicle_code' => 'KND-FILTER-01',
            'license_plate' => 'S 2001 AA',
            'brand' => 'Honda',
            'model' => 'Vario Filter',
            'status' => VehicleStatus::Damaged,
            'registration_expiry_date' => '2026-08-20',
            'storage_location' => 'Garasi Timur',
        ]);
        $other = $this->vehicle([
            'vehicle_code' => 'KND-FILTER-02',
            'license_plate' => 'S 2002 AB',
            'brand' => 'Yamaha',
            'model' => 'NMax Aman',
            'status' => VehicleStatus::Available,
            'registration_expiry_date' => '2027-09-01',
            'storage_location' => 'Garasi Barat',
        ]);

        $this->actingAs($admin)
            ->get(route('vehicles.index', [
                'q' => 'Garasi Timur',
                'status' => VehicleStatus::Damaged->value,
                'registration' => 'expiring',
            ]))
            ->assertOk()
            ->assertSee($matching->vehicle_code)
            ->assertDontSee($other->vehicle_code)
            ->assertSee('Segera berakhir');
    }

    public function test_employee_only_sees_active_vehicles_and_cannot_manage_master(): void
    {
        $employee = $this->employee();
        $activeVehicle = $this->vehicle([
            'vehicle_code' => 'KND-AKTIF-01',
            'license_plate' => 'S 3001 AA',
        ]);
        $inactiveVehicle = $this->vehicle([
            'vehicle_code' => 'KND-NONAKTIF-01',
            'license_plate' => 'S 3002 AB',
            'status' => VehicleStatus::Inactive,
            'is_active' => false,
        ]);

        $this->actingAs($employee)
            ->get(route('vehicles.index'))
            ->assertOk()
            ->assertSee($activeVehicle->vehicle_code)
            ->assertDontSee($inactiveVehicle->vehicle_code);

        $this->actingAs($employee)
            ->get(route('vehicles.show', $inactiveVehicle))
            ->assertNotFound();

        $this->actingAs($employee)
            ->get(route('vehicles.create'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->post(route('vehicles.store'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_deactivation_and_activation_keep_history_and_write_audit(): void
    {
        $admin = $this->admin();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Damaged,
        ]);

        $this->actingAs($admin)
            ->patch(route('vehicles.deactivate', $vehicle))
            ->assertRedirect();

        $vehicle->refresh();
        $this->assertFalse($vehicle->is_active);
        $this->assertSame(VehicleStatus::Inactive, $vehicle->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_deactivated',
            'auditable_id' => $vehicle->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('vehicles.activate', $vehicle))
            ->assertRedirect();

        $vehicle->refresh();
        $this->assertTrue($vehicle->is_active);
        $this->assertSame(VehicleStatus::Available, $vehicle->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_activated',
            'auditable_id' => $vehicle->id,
        ]);
    }

    public function test_transaction_controlled_status_cannot_be_changed_or_deactivated(): void
    {
        $admin = $this->admin();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Borrowed,
        ]);

        $this->actingAs($admin)
            ->from(route('vehicles.edit', $vehicle))
            ->put(route('vehicles.update', $vehicle), $this->validPayload([
                'vehicle_code' => $vehicle->vehicle_code,
                'license_plate' => $vehicle->license_plate,
                'chassis_number' => $vehicle->chassis_number,
                'engine_number' => $vehicle->engine_number,
                'status' => VehicleStatus::Available->value,
                'remove_image' => '0',
            ]))
            ->assertRedirect(route('vehicles.edit', $vehicle))
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->from(route('vehicles.edit', $vehicle))
            ->put(route('vehicles.update', $vehicle), $this->validPayload([
                'vehicle_code' => $vehicle->vehicle_code,
                'license_plate' => $vehicle->license_plate,
                'chassis_number' => $vehicle->chassis_number,
                'engine_number' => $vehicle->engine_number,
                'current_odometer' => '1100.0',
                'status' => VehicleStatus::Borrowed->value,
                'remove_image' => '0',
            ]))
            ->assertRedirect(route('vehicles.edit', $vehicle))
            ->assertSessionHasErrors('current_odometer');

        $this->actingAs($admin)
            ->from(route('vehicles.show', $vehicle))
            ->patch(route('vehicles.deactivate', $vehicle))
            ->assertRedirect(route('vehicles.show', $vehicle))
            ->assertSessionHasErrors('vehicle');

        $vehicle->refresh();
        $this->assertTrue($vehicle->is_active);
        $this->assertSame(VehicleStatus::Borrowed, $vehicle->status);
    }

    public function test_vehicle_detail_displays_audit_time_in_wib(): void
    {
        Carbon::setTestNow('2026-08-03 02:15:00 UTC');
        $admin = $this->admin();
        $vehicle = $this->vehicle();

        $this->actingAs($admin)
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('09:15')
            ->assertSee('WIB');
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
    private function vehicle(array $attributes = []): Vehicle
    {
        return Vehicle::query()->create([
            'vehicle_code' => fake()->unique()->bothify('KND-###??'),
            'license_plate' => fake()->unique()->bothify('S #### ??'),
            'brand' => 'Honda',
            'model' => 'Vario 160 CBS',
            'year' => 2025,
            'color' => 'Hitam',
            'chassis_number' => fake()->unique()->bothify('MH1###############'),
            'engine_number' => fake()->unique()->bothify('ENG############'),
            'current_odometer' => 1000.0,
            'status' => VehicleStatus::Available,
            'registration_expiry_date' => '2027-08-03',
            'storage_location' => 'Garasi Kantor, Slot A-01',
            'responsible_person' => 'Pengelola Barang',
            'image_path' => null,
            'notes' => null,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'vehicle_code' => fake()->unique()->bothify('KND-###??'),
            'license_plate' => fake()->unique()->bothify('S #### ??'),
            'brand' => 'Honda',
            'model' => 'Vario 160 CBS',
            'year' => '2025',
            'color' => 'Hitam',
            'chassis_number' => fake()->unique()->bothify('MH1###############'),
            'engine_number' => fake()->unique()->bothify('ENG############'),
            'current_odometer' => '1000.0',
            'status' => VehicleStatus::Available->value,
            'registration_expiry_date' => '2027-08-03',
            'storage_location' => 'Garasi Kantor, Slot A-01',
            'responsible_person' => 'Pengelola Barang',
            'notes' => 'Kendaraan operasional kantor.',
            'is_active' => '1',
            ...$overrides,
        ];
    }
}

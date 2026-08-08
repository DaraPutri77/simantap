<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\InventoryRequestStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\RoleName;
use App\Enums\VehicleLoanStatus;
use App\Enums\VehicleStatus;
use App\Models\InventoryRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\MaintenanceRecord;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoan;
use App\Services\NotificationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        Carbon::setTestNow('2026-08-08 04:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_inventory_submission_notifies_administrator(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $request = $this->inventoryRequest($employee);

        $request->forceFill([
            'status' => InventoryRequestStatus::Submitted,
            'submitted_at' => now(),
        ])->save();

        $this->assertSame(1, $admin->fresh()->unreadNotifications()->count());
        $this->assertSame(
            'inventory_request_submitted',
            data_get($admin->fresh()->notifications()->first()?->data, 'event'),
        );
    }

    public function test_inventory_decision_notifies_request_owner(): void
    {
        $this->admin();
        $employee = $this->employee();
        $request = $this->inventoryRequest($employee, [
            'status' => InventoryRequestStatus::UnderReview,
        ]);

        $request->forceFill([
            'status' => InventoryRequestStatus::Approved,
            'approved_at' => now(),
        ])->save();

        $notification = $employee->fresh()->notifications()->firstOrFail();

        $this->assertSame(
            'inventory_request_approved',
            data_get($notification->data, 'event'),
        );
        $this->assertSame(
            'my.inventory-requests.show',
            data_get($notification->data, 'route_name'),
        );
    }

    public function test_vehicle_submission_and_approval_reach_correct_recipients(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle();
        $loan = $this->vehicleLoan($employee, $vehicle);

        $loan->forceFill([
            'status' => VehicleLoanStatus::Submitted,
            'submitted_at' => now(),
        ])->save();

        $this->assertSame(
            'vehicle_loan_submitted',
            data_get($admin->fresh()->notifications()->first()?->data, 'event'),
        );

        $loan->forceFill([
            'status' => VehicleLoanStatus::Approved,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ])->save();

        $this->assertSame(
            'vehicle_loan_approved',
            data_get($employee->fresh()->notifications()->first()?->data, 'event'),
        );
    }

    public function test_return_request_and_overdue_notify_operational_parties(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle(['status' => VehicleStatus::Borrowed]);
        $loan = $this->vehicleLoan($employee, $vehicle, [
            'status' => VehicleLoanStatus::Borrowed,
            'actual_start_at' => now()->subHours(4),
            'planned_end_at' => now()->subHour(),
        ]);

        $loan->forceFill([
            'status' => VehicleLoanStatus::AwaitingReturnInspection,
            'actual_end_at' => now(),
        ])->save();

        $this->assertTrue(
            $admin->fresh()->notifications->contains(
                fn ($notification): bool => data_get($notification->data, 'event') === 'vehicle_loan_return_requested',
            ),
        );

        $loan->forceFill(['overdue_at' => $loan->planned_end_at])->save();

        $this->assertTrue(
            $employee->fresh()->notifications->contains(
                fn ($notification): bool => data_get($notification->data, 'event') === 'vehicle_loan_overdue',
            ),
        );
        $this->assertTrue(
            $admin->fresh()->notifications->contains(
                fn ($notification): bool => data_get($notification->data, 'event') === 'vehicle_loan_overdue',
            ),
        );
    }

    public function test_linked_maintenance_progress_notifies_borrower(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle(['status' => VehicleStatus::Inspection]);
        $loan = $this->vehicleLoan($employee, $vehicle, [
            'status' => VehicleLoanStatus::ReturnIssue,
        ]);
        $employee->notifications()->delete();

        $maintenance = MaintenanceRecord::query()->create([
            'maintenance_number' => 'MTC/2026/08/0001',
            'vehicle_id' => $vehicle->id,
            'source_vehicle_loan_id' => $loan->id,
            'vehicle_snapshot' => $vehicle->displayName(),
            'vehicle_status_before' => VehicleStatus::Inspection,
            'reported_by' => $admin->id,
            'maintenance_type' => 'Perbaikan kendaraan',
            'complaint' => 'Kerusakan ditemukan saat pengembalian.',
            'initial_condition' => 'Perlu pemeriksaan lebih lanjut.',
            'reported_date' => now()->toDateString(),
            'status' => MaintenanceStatus::Reported,
        ]);

        $maintenance->forceFill([
            'status' => MaintenanceStatus::InProgress,
            'handled_by' => $admin->id,
            'start_date' => now()->toDateString(),
            'started_at' => now(),
        ])->save();

        $this->assertTrue(
            $employee->fresh()->notifications->contains(
                fn ($notification): bool => data_get($notification->data, 'event') === 'maintenance_in_progress',
            ),
        );
    }

    public function test_low_stock_crossing_notifies_admin_without_repeated_transition_noise(): void
    {
        $admin = $this->admin();
        $item = $this->item([
            'current_stock' => 10,
            'reserved_stock' => 0,
            'minimum_stock' => 5,
        ]);

        $item->forceFill(['current_stock' => 5])->save();

        $this->assertSame(1, $admin->fresh()->notifications()->count());
        $this->assertSame(
            'stock_low',
            data_get($admin->fresh()->notifications()->first()?->data, 'event'),
        );

        $item->forceFill(['current_stock' => 4])->save();

        $this->assertSame(1, $admin->fresh()->notifications()->count());
    }

    public function test_notification_index_only_displays_authenticated_users_notifications(): void
    {
        $owner = $this->employee(['name' => 'Pegawai Pemilik Notifikasi']);
        $other = $this->employee(['name' => 'Pegawai Notifikasi Lain']);
        $service = app(NotificationService::class);

        $this->sendDirectNotification($service, $owner, 'own_event', 'Pesan Milik Sendiri');
        $this->sendDirectNotification($service, $other, 'other_event', 'Pesan Pegawai Lain');

        $this->actingAs($owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Pesan Milik Sendiri')
            ->assertDontSee('Pesan Pegawai Lain');
    }

    public function test_user_cannot_open_another_users_notification(): void
    {
        $owner = $this->employee();
        $other = $this->employee();
        $service = app(NotificationService::class);
        $this->sendDirectNotification($service, $owner, 'private_event', 'Notifikasi Privat');
        $notification = $owner->fresh()->notifications()->firstOrFail();

        $this->actingAs($other)
            ->get(route('notifications.open', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_opening_notification_marks_it_read_and_redirects_to_internal_route(): void
    {
        $employee = $this->employee();
        $service = app(NotificationService::class);
        $this->sendDirectNotification(
            $service,
            $employee,
            'profile_event',
            'Periksa Profil',
            'profile.show',
        );
        $notification = $employee->fresh()->notifications()->firstOrFail();

        $this->actingAs($employee)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('profile.show'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_own_notifications_as_read(): void
    {
        $employee = $this->employee();
        $service = app(NotificationService::class);
        $this->sendDirectNotification($service, $employee, 'first_event', 'Pertama');
        $this->sendDirectNotification($service, $employee, 'second_event', 'Kedua');

        $this->assertSame(2, $employee->fresh()->unreadNotifications()->count());

        $this->actingAs($employee)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $employee->fresh()->unreadNotifications()->count());
    }

    public function test_application_layout_renders_notification_bell_and_unread_payload(): void
    {
        $employee = $this->employee();
        $service = app(NotificationService::class);
        $this->sendDirectNotification($service, $employee, 'bell_event', 'Notifikasi Uji Bell');

        $this->actingAs($employee)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Buka notifikasi')
            ->assertSee('Notifikasi Uji Bell');
    }

    public function test_operational_dispatch_command_is_deduplicated_for_due_overdue_and_low_stock(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $dueVehicle = $this->vehicle();
        $overdueVehicle = $this->vehicle();

        $this->vehicleLoan($employee, $dueVehicle, [
            'status' => VehicleLoanStatus::Borrowed,
            'planned_start_at' => now()->subHour(),
            'planned_end_at' => now()->addHours(4),
            'actual_start_at' => now()->subHour(),
        ]);
        $this->vehicleLoan($employee, $overdueVehicle, [
            'status' => VehicleLoanStatus::Borrowed,
            'planned_start_at' => now()->subHours(5),
            'planned_end_at' => now()->subHour(),
            'actual_start_at' => now()->subHours(5),
        ]);
        $this->item([
            'item_code' => 'BRG-LOW',
            'name' => 'Barang Stok Minimum',
            'current_stock' => 2,
            'minimum_stock' => 5,
        ]);

        $this->artisan('simantap:notifications:dispatch')->assertSuccessful();

        $employeeCount = $employee->fresh()->notifications()->count();
        $adminCount = $admin->fresh()->notifications()->count();

        $this->assertSame(2, $employeeCount);
        $this->assertSame(2, $adminCount);

        $this->artisan('simantap:notifications:dispatch')->assertSuccessful();

        $this->assertSame($employeeCount, $employee->fresh()->notifications()->count());
        $this->assertSame($adminCount, $admin->fresh()->notifications()->count());
    }

    private function sendDirectNotification(
        NotificationService $service,
        User $recipient,
        string $event,
        string $title,
        string $routeName = 'notifications.index',
    ): void {
        $service->notify(
            recipient: $recipient,
            event: $event,
            module: 'test',
            title: $title,
            message: 'Pesan pengujian notifikasi SIMANTAP.',
            level: 'info',
            routeName: $routeName,
            routeParams: [],
            resourceType: User::class,
            resourceId: $recipient->id,
        );
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
            'phone' => fake()->unique()->numerify('0812########'),
            'work_unit' => 'Seksi Statistik Produksi',
            ...$attributes,
        ]);
        $user->assignRole(RoleName::Employee->value);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function inventoryRequest(
        User $employee,
        array $attributes = [],
    ): InventoryRequest {
        return InventoryRequest::query()->create([
            'request_number' => fake()->unique()->numerify('REQ/2026/08/####'),
            'requested_by' => $employee->id,
            'employee_number_snapshot' => $employee->employee_number,
            'requester_name_snapshot' => $employee->name,
            'work_unit_snapshot' => $employee->work_unit,
            'request_date' => now(),
            'purpose' => 'Kebutuhan operasional pengujian notifikasi.',
            'status' => InventoryRequestStatus::Draft,
            ...$attributes,
        ]);
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
            'current_odometer' => 1000,
            'status' => VehicleStatus::Available,
            'registration_expiry_date' => '2027-08-08',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function vehicleLoan(
        User $employee,
        Vehicle $vehicle,
        array $attributes = [],
    ): VehicleLoan {
        return VehicleLoan::query()->create([
            'loan_number' => fake()->unique()->numerify('LOAN/2026/08/####'),
            'borrower_id' => $employee->id,
            'borrower_name_snapshot' => $employee->name,
            'employee_number_snapshot' => $employee->employee_number,
            'work_unit_snapshot' => $employee->work_unit,
            'phone_snapshot' => $employee->phone,
            'vehicle_id' => $vehicle->id,
            'vehicle_code_snapshot' => $vehicle->vehicle_code,
            'license_plate_snapshot' => $vehicle->license_plate,
            'vehicle_name_snapshot' => $vehicle->displayName(),
            'purpose' => 'Kegiatan lapangan pengujian notifikasi.',
            'destination' => 'Kantor Kecamatan',
            'planned_start_at' => now()->addHour(),
            'planned_end_at' => now()->addHours(5),
            'status' => VehicleLoanStatus::Draft,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function item(array $attributes = []): Item
    {
        $category = ItemCategory::query()->create([
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'is_active' => true,
        ]);
        $unit = Unit::query()->create([
            'name' => fake()->unique()->word(),
            'symbol' => fake()->unique()->lexify('???'),
            'is_active' => true,
        ]);

        return Item::query()->create([
            'item_code' => fake()->unique()->bothify('BRG-###??'),
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => fake()->words(3, true),
            'current_stock' => 10,
            'reserved_stock' => 0,
            'minimum_stock' => 5,
            'storage_location' => 'Gudang Utama',
            'is_active' => true,
            ...$attributes,
        ]);
    }
}

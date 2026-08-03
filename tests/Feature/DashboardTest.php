<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\InventoryRequestStatus;
use App\Enums\VehicleStatus;
use App\Models\InventoryRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_admin_can_view_administrator_dashboard(): void
    {
        $admin = User::factory()->create([
            'status' => AccountStatus::Active,
            'must_change_password' => false,
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard Administrator')
            ->assertSee('Semua aset dalam satu kendali.')
            ->assertSee('Akses akun Anda')
            ->assertDontSee('Detail permission akun')
            ->assertDontSee('permission aktif')
            ->assertDontSee('6/6 modul')
            ->assertDontSee('href="#"', false);
    }

    public function test_employee_can_view_personal_dashboard(): void
    {
        $employee = User::factory()->create([
            'name' => 'Budi Santoso',
            'status' => AccountStatus::Active,
            'must_change_password' => false,
        ]);
        $employee->assignRole('pegawai');

        $this->actingAs($employee)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard Saya')
            ->assertSee('Halo, Budi Santoso!')
            ->assertSee('Profil')
            ->assertDontSee('Manajemen Pengguna')
            ->assertDontSee('Audit Log')
            ->assertDontSee('inventory-request.view-own');
    }

    public function test_admin_dashboard_displays_database_operational_data(): void
    {
        $admin = $this->activeUser([
            'name' => 'Administrator Operasional',
        ]);
        $admin->assignRole('admin');

        $employee = $this->activeUser([
            'name' => 'Siti Rahma',
            'employee_number' => 'PEG-001',
            'email' => 'siti.rahma@example.test',
        ]);
        $employee->assignRole('pegawai');

        $category = ItemCategory::query()->create([
            'name' => 'Kertas Pengujian',
            'description' => 'Kategori pengujian dashboard.',
            'is_active' => true,
        ]);
        $unit = Unit::query()->create([
            'name' => 'Rim Pengujian',
            'symbol' => 'rim-test',
            'is_active' => true,
        ]);
        $item = Item::query()->create([
            'item_code' => 'BRG-TEST-001',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Kertas A4 Pengujian',
            'minimum_stock' => 5,
            'is_active' => true,
        ]);
        $item->forceFill([
            'current_stock' => 2,
            'reserved_stock' => 0,
        ])->save();
        Vehicle::query()->create([
            'vehicle_code' => 'MTR-TEST-001',
            'license_plate' => 'N 1001 TEST',
            'brand' => 'Honda',
            'model' => 'Vario',
            'status' => VehicleStatus::Available,
            'is_active' => true,
        ]);
        InventoryRequest::query()->create([
            'request_number' => 'REQ/TEST/0001',
            'requested_by' => $employee->id,
            'employee_number_snapshot' => $employee->employee_number,
            'requester_name_snapshot' => $employee->name,
            'work_unit_snapshot' => $employee->work_unit,
            'request_date' => now(),
            'purpose' => 'Kebutuhan operasional pengujian',
            'status' => InventoryRequestStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Kertas A4 Pengujian')
            ->assertSee('REQ/TEST/0001')
            ->assertSee('Siti Rahma')
            ->assertSee('Stok Hampir Habis')
            ->assertSee('Aktivitas Persediaan')
            ->assertSee('Aktivitas Kendaraan');
    }

    public function test_employee_dashboard_only_displays_own_activity(): void
    {
        $employee = $this->activeUser([
            'name' => 'Budi Santoso',
            'employee_number' => 'PEG-010',
            'email' => 'budi.santoso@example.test',
        ]);
        $employee->assignRole('pegawai');

        $otherEmployee = $this->activeUser([
            'name' => 'Pegawai Lain',
            'employee_number' => 'PEG-011',
            'email' => 'pegawai.lain@example.test',
        ]);
        $otherEmployee->assignRole('pegawai');

        InventoryRequest::query()->create([
            'request_number' => 'REQ/MILIK/0001',
            'requested_by' => $employee->id,
            'employee_number_snapshot' => $employee->employee_number,
            'requester_name_snapshot' => $employee->name,
            'work_unit_snapshot' => $employee->work_unit,
            'request_date' => now(),
            'purpose' => 'Permintaan milik Budi',
            'status' => InventoryRequestStatus::Submitted,
            'submitted_at' => now(),
        ]);
        InventoryRequest::query()->create([
            'request_number' => 'REQ/LAIN/0001',
            'requested_by' => $otherEmployee->id,
            'employee_number_snapshot' => $otherEmployee->employee_number,
            'requester_name_snapshot' => $otherEmployee->name,
            'work_unit_snapshot' => $otherEmployee->work_unit,
            'request_date' => now(),
            'purpose' => 'Permintaan pegawai lain',
            'status' => InventoryRequestStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('REQ/MILIK/0001')
            ->assertSee('Permintaan milik Budi')
            ->assertDontSee('REQ/LAIN/0001')
            ->assertDontSee('Permintaan pegawai lain');
    }

    public function test_dashboard_activity_time_is_displayed_in_current_wib(): void
    {
        $employee = $this->activeUser([
            'name' => 'Pegawai Zona Waktu',
            'employee_number' => 'PEG-WIB-001',
            'email' => 'pegawai.wib@example.test',
        ]);
        $employee->assignRole('pegawai');
        $utcMoment = CarbonImmutable::create(
            2026,
            8,
            3,
            2,
            47,
            0,
            'UTC',
        );

        InventoryRequest::query()->create([
            'request_number' => 'REQ/WIB/0001',
            'requested_by' => $employee->id,
            'employee_number_snapshot' => $employee->employee_number,
            'requester_name_snapshot' => $employee->name,
            'work_unit_snapshot' => $employee->work_unit,
            'request_date' => $utcMoment,
            'purpose' => 'Validasi jam aktivitas WIB',
            'status' => InventoryRequestStatus::Submitted,
            'submitted_at' => $utcMoment,
        ]);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('09:47 WIB')
            ->assertDontSee('02:47 WIB');
    }

    public function test_user_can_open_complete_profile_page(): void
    {
        $employee = $this->activeUser([
            'employee_number' => 'PEG-PROFIL-001',
            'name' => 'Rina Puspita',
            'email' => 'rina.puspita@example.test',
            'phone' => '081234567890',
            'work_unit' => 'Statistik Sosial',
            'position' => 'Statistisi',
        ]);
        $employee->assignRole('pegawai');

        $this->actingAs($employee)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Profil Saya')
            ->assertSee('PEG-PROFIL-001')
            ->assertSee('Rina Puspita')
            ->assertSee('rina.puspita@example.test')
            ->assertSee('081234567890')
            ->assertSee('Statistik Sosial')
            ->assertSee('Statistisi')
            ->assertSee('Ubah Kata Sandi');
    }

    public function test_suspended_authenticated_user_is_logged_out(): void
    {
        $employee = User::factory()->create([
            'status' => AccountStatus::Suspended,
        ]);
        $employee->assignRole('pegawai');

        $this->actingAs($employee)
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_temporary_password_forces_password_change(): void
    {
        $admin = User::factory()->create([
            'status' => AccountStatus::Active,
            'must_change_password' => true,
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect(route('password.change'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function activeUser(array $attributes = []): User
    {
        return User::factory()->create([
            'status' => AccountStatus::Active,
            'must_change_password' => false,
            ...$attributes,
        ]);
    }
}

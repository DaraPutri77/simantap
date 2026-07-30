<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_employee_can_open_profile_edit_page(): void
    {
        $employee = $this->activeEmployee();

        $this->actingAs($employee)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Perbarui Profil')
            ->assertSee('Simpan Perubahan')
            ->assertSee('Dikelola oleh Administrator');
    }

    public function test_employee_can_update_own_personal_profile(): void
    {
        $employee = $this->activeEmployee([
            'name' => 'Nama Lama',
            'email' => 'lama@example.test',
            'phone' => '081111111111',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($employee)
            ->put(route('profile.update'), [
                'name' => '  Nama Baru  ',
                'email' => 'BARU@EXAMPLE.TEST',
                'phone' => '0812-3456-7890',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas(
                'status',
                'Profil berhasil diperbarui.',
            );

        $updatedEmployee = $employee->fresh();

        $this->assertSame('Nama Baru', $updatedEmployee->name);
        $this->assertSame('baru@example.test', $updatedEmployee->email);
        $this->assertSame('081234567890', $updatedEmployee->phone);
        $this->assertNull($updatedEmployee->email_verified_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $employee->id,
            'event' => 'profile_updated',
            'module' => 'profile',
            'auditable_type' => 'user',
            'auditable_id' => $employee->id,
        ]);
    }

    public function test_employee_cannot_change_admin_managed_fields(): void
    {
        $employee = $this->activeEmployee([
            'employee_number' => 'PEG-001',
            'work_unit' => 'Umum',
            'position' => 'Pegawai',
            'status' => AccountStatus::Active,
        ]);

        $this->actingAs($employee)
            ->put(route('profile.update'), [
                'name' => $employee->name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'employee_number' => 'ADMIN-001',
                'work_unit' => 'Administrator',
                'position' => 'Kepala',
                'status' => AccountStatus::Suspended->value,
                'role' => RoleName::Administrator->value,
            ])
            ->assertRedirect(route('profile.show'));

        $updatedEmployee = $employee->fresh();

        $this->assertSame('PEG-001', $updatedEmployee->employee_number);
        $this->assertSame('Umum', $updatedEmployee->work_unit);
        $this->assertSame('Pegawai', $updatedEmployee->position);
        $this->assertSame(
            AccountStatus::Active,
            $updatedEmployee->status,
        );
        $this->assertTrue(
            $updatedEmployee->hasRole(RoleName::Employee->value),
        );
        $this->assertFalse(
            $updatedEmployee->hasRole(
                RoleName::Administrator->value,
            ),
        );
    }

    public function test_profile_update_validates_unique_email_and_phone(): void
    {
        $employee = $this->activeEmployee();
        $otherEmployee = $this->activeEmployee([
            'email' => 'sudah.dipakai@example.test',
        ]);

        $this->actingAs($employee)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => 'Pegawai SIMANTAP',
                'email' => $otherEmployee->email,
                'phone' => '123',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors([
                'email',
                'phone',
            ]);
    }

    public function test_last_successful_login_is_displayed_in_jakarta_time(): void
    {
        $employee = $this->activeEmployee([
            'last_login_at' => CarbonImmutable::create(
                2026,
                7,
                30,
                4,
                46,
                0,
                'UTC',
            ),
        ]);

        $this->actingAs($employee)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Login berhasil terakhir')
            ->assertSee('30 Juli 2026, 11:46 WIB');
    }

    public function test_guest_cannot_open_or_update_profile(): void
    {
        $this->get(route('profile.show'))
            ->assertRedirect(route('login'));

        $this->get(route('profile.edit'))
            ->assertRedirect(route('login'));

        $this->put(route('profile.update'), [
            'name' => 'Tanpa Login',
            'email' => 'guest@example.test',
            'phone' => '081234567890',
        ])->assertRedirect(route('login'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function activeEmployee(array $attributes = []): User
    {
        $employee = User::factory()->create([
            'status' => AccountStatus::Active,
            'must_change_password' => false,
            ...$attributes,
        ]);

        $employee->assignRole(RoleName::Employee->value);

        return $employee;
    }
}

<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            RoleAndPermissionSeeder::class,
        );
    }

    public function test_seeder_creates_authorization_contract(): void
    {
        $this->assertDatabaseCount(
            'roles',
            count(RoleName::cases()),
        );

        $this->assertDatabaseCount(
            'permissions',
            count(PermissionName::cases()),
        );

        foreach (RoleName::cases() as $roleName) {
            $this->assertDatabaseHas(
                'roles',
                [
                    'name' => $roleName->value,
                    'guard_name' => 'web',
                ],
            );
        }

        foreach (
            PermissionName::cases() as $permissionName
        ) {
            $this->assertDatabaseHas(
                'permissions',
                [
                    'name' => $permissionName->value,
                    'guard_name' => 'web',
                ],
            );
        }
    }

    public function test_administrator_receives_every_permission(): void
    {
        $administrator = $this->activeUser();

        $administrator->assignRole(
            RoleName::Administrator->value,
        );

        foreach (
            PermissionName::cases() as $permissionName
        ) {
            $this->assertTrue(
                $administrator->can(
                    $permissionName->value,
                ),
                "Administrator tidak memiliki permission {$permissionName->value}.",
            );
        }
    }

    public function test_employee_receives_only_employee_permissions(): void
    {
        $employee = $this->activeUser();

        $employee->assignRole(
            RoleName::Employee->value,
        );

        $expectedPermissions = RoleName::Employee
            ->permissionValues();

        $actualPermissions = $employee
            ->getAllPermissions()
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing(
            $expectedPermissions,
            $actualPermissions,
        );

        $this->assertFalse(
            $employee->can(
                PermissionName::UserView->value,
            ),
        );

        $this->assertFalse(
            $employee->can(
                PermissionName::AuditLogView->value,
            ),
        );

        $this->assertTrue(
            $employee->can(
                PermissionName::InventoryRequestCreate
                    ->value,
            ),
        );

        $this->assertTrue(
            $employee->can(
                PermissionName::VehicleLoanCreate->value,
            ),
        );
    }

    public function test_administrator_can_access_dashboard(): void
    {
        $administrator = $this->activeUser();

        $administrator->assignRole(
            RoleName::Administrator->value,
        );

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($administrator->name)
            ->assertSee('Administrator')
            ->assertSee('Akses akun Anda');
    }

    public function test_employee_can_access_dashboard(): void
    {
        $employee = $this->activeUser();

        $employee->assignRole(
            RoleName::Employee->value,
        );

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($employee->name)
            ->assertSee('Pegawai')
            ->assertSee('Akses akun Anda');
    }

    public function test_active_user_without_role_cannot_access_dashboard(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    private function activeUser(): User
    {
        return User::factory()->create([
            'status' => AccountStatus::Active,
            'must_change_password' => false,
            'email_verified_at' => now(),
            'activated_at' => now(),
            'password_changed_at' => now(),
        ]);
    }
}

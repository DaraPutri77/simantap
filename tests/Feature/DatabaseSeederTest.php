<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\ItemCategory;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_final_application_data(): void
    {
        $this->configureInitialData();

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('roles', count(RoleName::cases()));
        $this->assertDatabaseCount(
            'permissions',
            count(PermissionName::cases()),
        );

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findByName(
                $roleName->value,
                'web',
            )->load('permissions');

            $this->assertEqualsCanonicalizing(
                $roleName->permissionValues(),
                $role->permissions->pluck('name')->all(),
            );
        }

        $admin = User::query()
            ->where('email', 'admin.seed@test.local')
            ->firstOrFail();

        $this->assertSame('ADMIN-SEED-001', $admin->employee_number);
        $this->assertSame('Administrator Pengujian', $admin->name);
        $this->assertSame(AccountStatus::Active, $admin->status);
        $this->assertTrue($admin->must_change_password);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertNotNull($admin->activated_at);
        $this->assertNull($admin->password_changed_at);
        $this->assertTrue(
            Hash::check(
                'AdminSeeder!2026',
                $admin->password,
            ),
        );
        $this->assertTrue(
            $admin->hasRole(
                RoleName::Administrator->value,
            ),
        );
        $this->assertTrue(
            $admin->hasAllPermissions(
                PermissionName::values(),
            ),
        );

        foreach ($this->categoryNames() as $categoryName) {
            $this->assertDatabaseHas('item_categories', [
                'name' => $categoryName,
                'is_active' => true,
                'deleted_at' => null,
            ]);
        }

        foreach ($this->units() as $unit) {
            $this->assertDatabaseHas('units', [
                'name' => $unit['name'],
                'symbol' => $unit['symbol'],
                'is_active' => true,
                'deleted_at' => null,
            ]);
        }

        $this->assertDatabaseCount('item_categories', 6);
        $this->assertDatabaseCount('units', 6);
        $this->assertDatabaseCount('settings', 5);

        $this->assertSame(
            ['text' => 'Badan Pusat Statistik'],
            Setting::query()
                ->where('key', 'organization.name')
                ->firstOrFail()
                ->value,
        );
        $this->assertSame(
            ['text' => 'Asia/Jakarta'],
            Setting::query()
                ->where('key', 'system.display_timezone')
                ->firstOrFail()
                ->value,
        );
        $this->assertSame(
            ['number' => 3],
            Setting::query()
                ->where('key', 'vehicle.max_loan_days')
                ->firstOrFail()
                ->value,
        );
    }

    public function test_database_seeder_is_safe_to_run_repeatedly(): void
    {
        $this->configureInitialData();

        $this->seed(DatabaseSeeder::class);

        $admin = User::query()
            ->where('email', 'admin.seed@test.local')
            ->firstOrFail();
        $adminId = $admin->id;

        $admin->forceFill([
            'password' => 'ChangedByAdministrator!2026',
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        $setting = Setting::query()
            ->where('key', 'vehicle.max_loan_days')
            ->firstOrFail();
        $settingId = $setting->id;
        $setting->update([
            'value' => ['number' => 5],
        ]);

        $category = ItemCategory::query()
            ->where('name', 'Kertas')
            ->firstOrFail();
        $categoryId = $category->id;
        $category->delete();

        $unit = Unit::query()
            ->where('symbol', 'rim')
            ->firstOrFail();
        $unitId = $unit->id;
        $unit->delete();

        $this->seed(DatabaseSeeder::class);

        $admin->refresh();
        $setting->refresh();

        $this->assertSame($adminId, $admin->id);
        $this->assertFalse($admin->must_change_password);
        $this->assertTrue(
            Hash::check(
                'ChangedByAdministrator!2026',
                $admin->password,
            ),
        );
        $this->assertSame(['number' => 5], $setting->value);
        $this->assertSame($settingId, $setting->id);

        $restoredCategory = ItemCategory::query()
            ->where('name', 'Kertas')
            ->firstOrFail();
        $restoredUnit = Unit::query()
            ->where('symbol', 'rim')
            ->firstOrFail();

        $this->assertSame($categoryId, $restoredCategory->id);
        $this->assertTrue($restoredCategory->is_active);
        $this->assertSame($unitId, $restoredUnit->id);
        $this->assertTrue($restoredUnit->is_active);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('roles', count(RoleName::cases()));
        $this->assertDatabaseCount(
            'permissions',
            count(PermissionName::cases()),
        );
        $this->assertDatabaseCount('item_categories', 6);
        $this->assertDatabaseCount('units', 6);
        $this->assertDatabaseCount('settings', 5);
    }

    private function configureInitialData(): void
    {
        config()->set([
            'simantap.name' => 'SIMANTAP',
            'simantap.institution.name' => 'Badan Pusat Statistik',
            'simantap.institution.short_name' => 'BPS',
            'simantap.display_timezone' => 'Asia/Jakarta',
            'simantap.vehicle.max_loan_days' => 3,
            'simantap.security.password_min_length' => 12,
            'simantap.admin' => [
                'employee_number' => 'ADMIN-SEED-001',
                'name' => 'Administrator Pengujian',
                'email' => 'admin.seed@test.local',
                'password' => 'AdminSeeder!2026',
                'work_unit' => 'BPS',
                'position' => 'Administrator Sistem',
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function categoryNames(): array
    {
        return [
            'Alat Tulis Kantor',
            'Kertas',
            'Tinta',
            'Perlengkapan Kebersihan',
            'Barang Cetakan',
            'Perlengkapan Komputer',
        ];
    }

    /**
     * @return list<array{
     *     name: string,
     *     symbol: string
     * }>
     */
    private function units(): array
    {
        return [
            [
                'name' => 'Buah',
                'symbol' => 'buah',
            ],
            [
                'name' => 'Rim',
                'symbol' => 'rim',
            ],
            [
                'name' => 'Pak',
                'symbol' => 'pak',
            ],
            [
                'name' => 'Kotak',
                'symbol' => 'kotak',
            ],
            [
                'name' => 'Botol',
                'symbol' => 'botol',
            ],
            [
                'name' => 'Unit',
                'symbol' => 'unit',
            ],
        ];
    }
}

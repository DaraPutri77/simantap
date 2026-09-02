<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DemoEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_only_the_two_demo_accounts(): void
    {
        $this->configureDemoEnvironment();

        $this->seed(DemoSeeder::class);

        $this->assertDatabaseCount('users', 2);

        $administrator = User::query()
            ->where('email', 'admin@bps.go.id')
            ->firstOrFail();
        $employee = User::query()
            ->where('email', 'pegawai@bps.go.id')
            ->firstOrFail();

        $this->assertSame(
            AccountStatus::Active,
            $administrator->status,
        );
        $this->assertSame(
            AccountStatus::Active,
            $employee->status,
        );
        $this->assertFalse($administrator->must_change_password);
        $this->assertFalse($employee->must_change_password);
        $this->assertTrue(
            Hash::check('admin123', $administrator->password),
        );
        $this->assertTrue(
            Hash::check('pegawai123', $employee->password),
        );
        $this->assertEqualsCanonicalizing(
            [RoleName::Administrator->value],
            $administrator->roles()->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [RoleName::Employee->value],
            $employee->roles()->pluck('name')->all(),
        );
    }

    public function test_demo_seeder_is_safe_to_run_repeatedly(): void
    {
        $this->configureDemoEnvironment();

        $this->seed(DemoSeeder::class);
        $administratorId = User::query()
            ->where('email', 'admin@bps.go.id')
            ->value('id');

        $this->seed(DemoSeeder::class);

        $this->assertDatabaseCount('users', 2);
        $this->assertSame(
            $administratorId,
            User::query()
                ->where('email', 'admin@bps.go.id')
                ->value('id'),
        );
    }

    public function test_production_seeder_does_not_create_demo_accounts(): void
    {
        config()->set('simantap.admin', [
            'employee_number' => 'ADMIN-RESMI-001',
            'name' => 'Administrator Resmi',
            'email' => 'admin.resmi@bps.go.id',
            'password' => 'AdminResmi!2026',
            'work_unit' => 'BPS Kabupaten Jombang',
            'position' => 'Administrator Sistem',
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'admin.resmi@bps.go.id',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'admin@bps.go.id',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'pegawai@bps.go.id',
        ]);
    }

    public function test_demo_seeder_refuses_non_demo_environment(): void
    {
        $this->configureDemoEnvironment(changeEnvironment: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_ENV harus bernilai demo');

        $this->seed(DemoSeeder::class);
    }

    public function test_demo_seeder_refuses_the_production_database(): void
    {
        $this->configureDemoEnvironment();
        config()->set(
            'simantap.production_database',
            DB::connection()->getDatabaseName(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('adalah database resmi');

        $this->seed(DemoSeeder::class);
    }

    public function test_demo_seeder_refuses_a_database_name_mismatch(): void
    {
        $this->configureDemoEnvironment();
        config()->set(
            'simantap.demo.database',
            DB::connection()->getDatabaseName().'-berbeda',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak sama dengan database demo');

        $this->seed(DemoSeeder::class);
    }

    public function test_demo_reset_dry_run_does_not_write_anything(): void
    {
        $this->configureDemoEnvironment();

        $this->artisan('simantap:reset-demo')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_reset_requires_exact_confirmation(): void
    {
        $this->configureDemoEnvironment();

        $this->artisan('simantap:reset-demo', [
            '--execute' => true,
            '--confirmation' => 'RESET',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    private function configureDemoEnvironment(
        bool $changeEnvironment = true,
    ): void {
        if ($changeEnvironment) {
            $this->app['env'] = 'demo';
        }

        config()->set([
            'simantap.production_database' => 'simantap',
            'simantap.demo.enabled' => true,
            'simantap.demo.database' => DB::connection()->getDatabaseName(),
            'simantap.demo.accounts.administrator' => [
                'employee_number' => 'DEMO-ADMIN-001',
                'name' => 'Administrator Demo',
                'email' => 'admin@bps.go.id',
                'password' => 'admin123',
                'work_unit' => 'BPS Kabupaten Jombang - Demo',
                'position' => 'Administrator Demo',
            ],
            'simantap.demo.accounts.employee' => [
                'employee_number' => 'DEMO-PEGAWAI-001',
                'name' => 'Pegawai Demo',
                'email' => 'pegawai@bps.go.id',
                'password' => 'pegawai123',
                'work_unit' => 'BPS Kabupaten Jombang - Demo',
                'position' => 'Pegawai Demo',
            ],
        ]);
    }
}

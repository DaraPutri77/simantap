<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Models\User;
use App\Support\DemoEnvironmentGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DemoEnvironmentGuard::assertSafe();

        $administrator = $this->accountConfiguration(
            'administrator',
        );
        $employee = $this->accountConfiguration('employee');

        if ($administrator['email'] === $employee['email']) {
            throw new RuntimeException(
                'Email Administrator Demo dan Pegawai Demo harus berbeda.',
            );
        }

        if (
            $administrator['employee_number']
            === $employee['employee_number']
        ) {
            throw new RuntimeException(
                'Nomor pegawai Administrator Demo dan Pegawai Demo harus berbeda.',
            );
        }

        $this->call([
            RoleAndPermissionSeeder::class,
            ReferenceDataSeeder::class,
        ]);

        DB::transaction(function () use ($administrator, $employee): void {
            $this->seedAccount(
                $administrator,
                RoleName::Administrator,
            );
            $this->seedAccount(
                $employee,
                RoleName::Employee,
            );
        });
    }

    /**
     * @param  array{
     *     employee_number: string,
     *     name: string,
     *     email: string,
     *     password: string,
     *     work_unit: string,
     *     position: string
     * }  $configuration
     */
    private function seedAccount(
        array $configuration,
        RoleName $role,
    ): void {
        $user = User::withTrashed()
            ->where('email', $configuration['email'])
            ->orWhere(
                'employee_number',
                $configuration['employee_number'],
            )
            ->first() ?? new User;

        $user->forceFill([
            'employee_number' => $configuration['employee_number'],
            'name' => $configuration['name'],
            'email' => $configuration['email'],
            'phone' => null,
            'work_unit' => $configuration['work_unit'],
            'position' => $configuration['position'],
            'status' => AccountStatus::Active,
            'password' => Hash::make($configuration['password']),
            'must_change_password' => false,
            'email_verified_at' => now(),
            'activated_at' => now(),
            'password_changed_at' => now(),
            'last_login_at' => null,
            'created_by' => null,
            'deleted_at' => null,
        ])->save();

        $user->syncRoles([$role->value]);
    }

    /**
     * @return array{
     *     employee_number: string,
     *     name: string,
     *     email: string,
     *     password: string,
     *     work_unit: string,
     *     position: string
     * }
     */
    private function accountConfiguration(string $account): array
    {
        $configuration = [
            'employee_number' => trim(
                (string) config(
                    "simantap.demo.accounts.{$account}.employee_number",
                ),
            ),
            'name' => trim(
                (string) config(
                    "simantap.demo.accounts.{$account}.name",
                ),
            ),
            'email' => Str::lower(
                trim(
                    (string) config(
                        "simantap.demo.accounts.{$account}.email",
                    ),
                ),
            ),
            'password' => (string) config(
                "simantap.demo.accounts.{$account}.password",
            ),
            'work_unit' => trim(
                (string) config(
                    "simantap.demo.accounts.{$account}.work_unit",
                ),
            ),
            'position' => trim(
                (string) config(
                    "simantap.demo.accounts.{$account}.position",
                ),
            ),
        ];

        foreach ($configuration as $key => $value) {
            if ($value === '') {
                throw new RuntimeException(
                    "Konfigurasi akun demo `{$account}.{$key}` wajib diisi.",
                );
            }
        }

        if (
            filter_var(
                $configuration['email'],
                FILTER_VALIDATE_EMAIL,
            ) === false
        ) {
            throw new RuntimeException(
                "Konfigurasi email akun demo `{$account}` tidak valid.",
            );
        }

        if (mb_strlen($configuration['password']) < 8) {
            throw new RuntimeException(
                "Sandi akun demo `{$account}` minimal 8 karakter.",
            );
        }

        return $configuration;
    }
}

<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $configuration = $this->configuration();

        DB::transaction(function () use ($configuration): void {
            $candidates = User::withTrashed()
                ->where(function (Builder $query) use (
                    $configuration
                ): void {
                    $query
                        ->where(
                            'employee_number',
                            $configuration['employee_number'],
                        )
                        ->orWhere(
                            'email',
                            $configuration['email'],
                        );
                })
                ->lockForUpdate()
                ->get();

            if ($candidates->count() > 1) {
                throw new LogicException(
                    'NIP dan email Admin awal digunakan oleh dua akun '
                    .'yang berbeda.',
                );
            }

            /** @var User|null $admin */
            $admin = $candidates->first();

            if ($admin === null) {
                $admin = User::query()->create([
                    'employee_number' => $configuration['employee_number'],
                    'name' => $configuration['name'],
                    'email' => $configuration['email'],
                    'phone' => null,
                    'work_unit' => $configuration['work_unit'],
                    'position' => $configuration['position'],
                    'status' => AccountStatus::Active,
                    'password' => $configuration['password'],
                    'must_change_password' => true,
                    'email_verified_at' => now(),
                    'activated_at' => now(),
                    'password_changed_at' => null,
                    'last_login_at' => null,
                    'created_by' => null,
                ]);
            } else {
                if ($admin->trashed()) {
                    $admin->restore();
                }

                $admin->forceFill([
                    'employee_number' => $configuration['employee_number'],
                    'name' => $configuration['name'],
                    'email' => $configuration['email'],
                    'work_unit' => $configuration['work_unit'],
                    'position' => $configuration['position'],
                ])->save();
            }

            $admin->syncRoles([
                RoleName::Administrator->value,
            ]);
        });
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
    private function configuration(): array
    {
        $configuration = [
            'employee_number' => trim(
                (string) config(
                    'simantap.admin.employee_number',
                ),
            ),
            'name' => trim(
                (string) config('simantap.admin.name'),
            ),
            'email' => Str::lower(
                trim(
                    (string) config('simantap.admin.email'),
                ),
            ),
            'password' => (string) config(
                'simantap.admin.password',
            ),
            'work_unit' => trim(
                (string) config('simantap.admin.work_unit'),
            ),
            'position' => trim(
                (string) config('simantap.admin.position'),
            ),
        ];

        foreach (
            [
                'employee_number',
                'name',
                'email',
                'password',
                'work_unit',
                'position',
            ] as $requiredKey
        ) {
            if ($configuration[$requiredKey] === '') {
                throw new RuntimeException(
                    "Konfigurasi Admin `{$requiredKey}` wajib diisi.",
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
                'Konfigurasi email Admin tidak valid.',
            );
        }

        $minimumPasswordLength = (int) config(
            'simantap.security.password_min_length',
            12,
        );

        if (
            mb_strlen($configuration['password'])
                < $minimumPasswordLength
            || preg_match(
                '/[a-z]/',
                $configuration['password'],
            ) !== 1
            || preg_match(
                '/[A-Z]/',
                $configuration['password'],
            ) !== 1
            || preg_match(
                '/[0-9]/',
                $configuration['password'],
            ) !== 1
            || preg_match(
                '/[^a-zA-Z0-9]/',
                $configuration['password'],
            ) !== 1
        ) {
            throw new RuntimeException(
                'Password Admin awal harus memenuhi kebijakan '
                .'password SIMANTAP.',
            );
        }

        return $configuration;
    }
}

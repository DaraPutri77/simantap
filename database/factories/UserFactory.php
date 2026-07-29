<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_number' => fake()
                ->unique()
                ->numerify('199###############'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('08##########'),
            'work_unit' => fake()->randomElement([
                'Bagian Umum',
                'Statistik Sosial',
                'Statistik Produksi',
                'Statistik Distribusi',
                'Neraca Wilayah dan Analisis Statistik',
            ]),
            'position' => 'Pegawai',
            'status' => AccountStatus::Active,
            'password' => static::$password ??= Hash::make('Password123!'),
            'must_change_password' => false,
            'email_verified_at' => now(),
            'activated_at' => now(),
            'password_changed_at' => now(),
            'last_login_at' => null,
            'created_by' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function pendingActivation(): static
    {
        return $this->state(fn (): array => [
            'status' => AccountStatus::PendingActivation,
            'password' => null,
            'must_change_password' => false,
            'email_verified_at' => null,
            'activated_at' => null,
            'password_changed_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => AccountStatus::Inactive,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => AccountStatus::Suspended,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => [
            'email_verified_at' => null,
        ]);
    }
}

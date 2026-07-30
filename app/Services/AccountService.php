<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function __construct(
        private readonly AccountActivationService $activationService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createEmployee(
        array $data,
        User $creator,
    ): User {
        $user = DB::transaction(
            function () use (
                $data,
                $creator,
            ): User {
                $user = User::query()->create([
                    'employee_number' => $data['employee_number'],
                    'name' => $data['name'],
                    'email' => Str::lower(
                        $data['email'],
                    ),
                    'phone' => $data['phone'] ?? null,
                    'work_unit' => $data['work_unit'] ?? null,
                    'position' => $data['position'] ?? null,
                    'status' => AccountStatus::PendingActivation,
                    'password' => null,
                    'must_change_password' => false,
                    'created_by' => $creator->getKey(),
                ]);

                $user->assignRole(
                    RoleName::Employee->value,
                );

                return $user;
            },
            3,
        );

        $this->activationService
            ->sendActivationLink(
                $user,
                $creator,
            );

        return $user;
    }

    public function resendActivation(
        User $user,
        User $creator,
    ): void {
        if (
            $user->status
            !== AccountStatus::PendingActivation
        ) {
            throw ValidationException::withMessages([
                'user' => 'Tautan aktivasi hanya dapat dikirim ke akun pending.',
            ]);
        }

        $this->activationService
            ->sendActivationLink(
                $user,
                $creator,
            );
    }
}

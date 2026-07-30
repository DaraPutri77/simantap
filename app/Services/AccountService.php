<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function __construct(
        private readonly AccountActivationService $activationService,
        private readonly AccountPasswordService $passwordService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createEmployee(
        array $data,
        User $creator,
        ?Request $request = null,
        bool $sendActivation = true,
    ): User {
        $user = DB::transaction(function () use ($data, $creator): User {
            $user = User::query()->create([
                'employee_number' => $data['employee_number'],
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'] ?? null,
                'work_unit' => $data['work_unit'] ?? null,
                'position' => $data['position'] ?? null,
                'status' => AccountStatus::PendingActivation,
                'password' => null,
                'must_change_password' => false,
                'created_by' => $creator->getKey(),
            ]);

            $user->assignRole(RoleName::Employee->value);

            return $user;
        }, 3);

        $this->auditLogger->log(
            event: 'employee_account_created',
            module: 'user_management',
            auditable: $user,
            newValues: $user->only([
                'employee_number',
                'name',
                'email',
                'phone',
                'work_unit',
                'position',
                'status',
            ]),
            request: $request,
            actorId: $creator->getKey(),
        );

        if ($sendActivation) {
            $this->resendActivation(
                $user,
                $creator,
                $request,
            );
        }

        return $user;
    }

    public function resendActivation(
        User $user,
        User $creator,
        ?Request $request = null,
    ): void {
        if ($user->status !== AccountStatus::PendingActivation) {
            throw ValidationException::withMessages([
                'user' => 'Tautan aktivasi hanya dapat dikirim ke akun pending.',
            ]);
        }

        $this->activationService->sendActivationLink(
            $user,
            $creator,
        );

        $this->auditLogger->log(
            event: 'account_activation_link_sent',
            module: 'user_management',
            auditable: $user,
            newValues: [
                'email' => $user->email,
                'status' => $user->status->value,
            ],
            request: $request,
            actorId: $creator->getKey(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateEmployee(
        User $user,
        array $data,
        User $actor,
        ?Request $request = null,
    ): User {
        $managedAttributes = [
            'employee_number',
            'name',
            'email',
            'phone',
            'work_unit',
            'position',
            'email_verified_at',
        ];
        $oldValues = $user->only($managedAttributes);

        $updatedUser = DB::transaction(function () use (
            $user,
            $data,
        ): User {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUser->fill([
                'employee_number' => $data['employee_number'],
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'] ?? null,
                'work_unit' => $data['work_unit'] ?? null,
                'position' => $data['position'] ?? null,
            ]);

            if ($lockedUser->isDirty('email')) {
                $lockedUser->email_verified_at = null;
            }

            $lockedUser->save();

            return $lockedUser;
        }, 3);

        $changedAttributes = array_values(array_intersect(
            array_keys($updatedUser->getChanges()),
            $managedAttributes,
        ));
        $newValues = $updatedUser->only($changedAttributes);

        if ($newValues !== []) {
            $this->auditLogger->log(
                event: 'employee_account_updated',
                module: 'user_management',
                auditable: $updatedUser,
                oldValues: collect($oldValues)
                    ->only(array_keys($newValues))
                    ->all(),
                newValues: $newValues,
                request: $request,
                actorId: $actor->getKey(),
            );
        }

        return $updatedUser;
    }

    public function suspend(
        User $user,
        User $actor,
        ?Request $request = null,
    ): User {
        if ($user->status !== AccountStatus::Active) {
            throw ValidationException::withMessages([
                'user' => 'Hanya akun aktif yang dapat dinonaktifkan.',
            ]);
        }

        $updatedUser = $this->setStatus(
            $user,
            AccountStatus::Suspended,
        );

        $this->passwordService->revokeDatabaseSessions($updatedUser);

        $this->auditStatusChange(
            $updatedUser,
            $actor,
            AccountStatus::Active,
            AccountStatus::Suspended,
            'employee_account_suspended',
            $request,
        );

        return $updatedUser;
    }

    public function reactivate(
        User $user,
        User $actor,
        ?Request $request = null,
    ): User {
        if ($user->status !== AccountStatus::Suspended) {
            throw ValidationException::withMessages([
                'user' => 'Hanya akun nonaktif yang dapat diaktifkan kembali.',
            ]);
        }

        if (
            $user->password === null
            || $user->activated_at === null
        ) {
            throw ValidationException::withMessages([
                'user' => 'Akun belum pernah diaktivasi. Gunakan pengiriman ulang tautan aktivasi.',
            ]);
        }

        $updatedUser = $this->setStatus(
            $user,
            AccountStatus::Active,
        );

        $this->auditStatusChange(
            $updatedUser,
            $actor,
            AccountStatus::Suspended,
            AccountStatus::Active,
            'employee_account_reactivated',
            $request,
        );

        return $updatedUser;
    }

    public function sendPasswordReset(
        User $user,
        User $actor,
        ?Request $request = null,
    ): void {
        if ($user->status !== AccountStatus::Active) {
            throw ValidationException::withMessages([
                'user' => 'Tautan reset kata sandi hanya dapat dikirim ke akun aktif.',
            ]);
        }

        $status = Password::broker()->sendResetLink(
            ['email' => $user->email],
            static function (
                User $notifiable,
                string $token,
            ): void {
                $notifiable->notify(
                    new ResetPasswordNotification($token),
                );
            },
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'user' => 'Tautan reset belum dapat dikirim. Tunggu sebentar lalu coba kembali.',
            ]);
        }

        $this->auditLogger->log(
            event: 'password_reset_link_sent_by_admin',
            module: 'user_management',
            auditable: $user,
            newValues: ['email' => $user->email],
            request: $request,
            actorId: $actor->getKey(),
        );
    }

    private function setStatus(
        User $user,
        AccountStatus $status,
    ): User {
        return DB::transaction(function () use (
            $user,
            $status,
        ): User {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUser->forceFill([
                'status' => $status,
                'remember_token' => Str::random(60),
            ])->save();

            return $lockedUser;
        }, 3);
    }

    private function auditStatusChange(
        User $user,
        User $actor,
        AccountStatus $oldStatus,
        AccountStatus $newStatus,
        string $event,
        ?Request $request,
    ): void {
        $this->auditLogger->log(
            event: $event,
            module: 'user_management',
            auditable: $user,
            oldValues: ['status' => $oldStatus->value],
            newValues: ['status' => $newStatus->value],
            request: $request,
            actorId: $actor->getKey(),
        );
    }
}

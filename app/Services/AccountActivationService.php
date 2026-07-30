<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Models\AccountActivationToken;
use App\Models\User;
use App\Notifications\ActivateAccountNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class AccountActivationService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function sendActivationLink(
        User $user,
        ?User $creator = null,
    ): void {
        $token = $this->issueToken($user, $creator);

        $user->notify(
            new ActivateAccountNotification($token),
        );
    }

    public function issueToken(
        User $user,
        ?User $creator = null,
    ): string {
        $plainToken = Str::random(64);

        DB::transaction(function () use (
            $user,
            $creator,
            $plainToken,
        ): void {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedUser->status
                !== AccountStatus::PendingActivation
            ) {
                throw new LogicException(
                    'Tautan aktivasi hanya dapat dibuat untuk akun pending.',
                );
            }

            AccountActivationToken::query()->updateOrCreate(
                [
                    'user_id' => $lockedUser->getKey(),
                ],
                [
                    'token_hash' => $this->hash(
                        $plainToken,
                    ),
                    'expires_at' => now()->addMinutes(
                        (int) config(
                            'simantap.security.activation_expire_minutes',
                            60,
                        ),
                    ),
                    'used_at' => null,
                    'created_by' => $creator?->getKey(),
                ],
            );
        }, 3);

        return $plainToken;
    }

    public function userForValidToken(
        string $plainToken,
    ): ?User {
        $activationToken = AccountActivationToken::query()
            ->with('user')
            ->where(
                'token_hash',
                $this->hash($plainToken),
            )
            ->first();

        if (
            $activationToken === null
            || ! $activationToken->isUsable()
            || $activationToken->user === null
            || $activationToken->user->status
                !== AccountStatus::PendingActivation
        ) {
            return null;
        }

        return $activationToken->user;
    }

    public function activate(
        string $plainToken,
        #[\SensitiveParameter] string $password,
        Request $request,
    ): User {
        return DB::transaction(function () use (
            $plainToken,
            $password,
            $request,
        ): User {
            $activationToken = AccountActivationToken::query()
                ->where(
                    'token_hash',
                    $this->hash($plainToken),
                )
                ->lockForUpdate()
                ->first();

            if (
                $activationToken === null
                || ! $activationToken->isUsable()
            ) {
                $this->throwInvalidToken();
            }

            $user = User::query()
                ->whereKey(
                    $activationToken->user_id,
                )
                ->lockForUpdate()
                ->first();

            if (
                $user === null
                || $user->status
                    !== AccountStatus::PendingActivation
            ) {
                $this->throwInvalidToken();
            }

            $oldStatus = $user->status->value;

            $user->forceFill([
                'password' => $password,
                'status' => AccountStatus::Active,
                'must_change_password' => false,
                'email_verified_at' => now(),
                'activated_at' => now(),
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            $activationToken->forceFill([
                'used_at' => now(),
            ])->save();

            $this->auditLogger->log(
                event: 'account_activated',
                module: 'authentication',
                auditable: $user,
                oldValues: [
                    'status' => $oldStatus,
                ],
                newValues: [
                    'status' => AccountStatus::Active->value,
                    'activated_at' => $user
                        ->activated_at
                        ?->toIso8601String(),
                ],
                request: $request,
                actorId: $user->getKey(),
            );

            return $user;
        }, 3);
    }

    private function hash(
        #[\SensitiveParameter] string $plainToken,
    ): string {
        return hash('sha256', $plainToken);
    }

    private function throwInvalidToken(): never
    {
        throw ValidationException::withMessages([
            'token' => 'Tautan aktivasi tidak valid, kedaluwarsa, atau sudah digunakan.',
        ]);
    }
}

<?php

namespace App\Http\Requests\Auth;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'login' => [
                'required',
                'string',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    public function authenticate(
        AuditLogger $auditLogger,
        NotificationService $notificationService,
    ): User {
        $this->ensureIsNotRateLimited(
            $auditLogger,
            $notificationService,
        );

        $field = $this->loginField();
        $identifier = $this->normalizedLogin();

        if (! Auth::guard('web')->attempt(
            [
                $field => $identifier,
                'password' => $this
                    ->string('password')
                    ->toString(),
            ],
            false,
        )) {
            RateLimiter::hit(
                $this->throttleKey(),
                $this->decaySeconds(),
            );

            $auditLogger->log(
                event: 'login_failed',
                module: 'authentication',
                newValues: [
                    'identifier' => $identifier,
                ],
                request: $this,
            );

            throw ValidationException::withMessages([
                'login' => 'Email, NIP, atau kata sandi tidak sesuai.',
            ]);
        }

        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        if (
            $user === null
            || $user->status !== AccountStatus::Active
        ) {
            if ($user !== null) {
                $auditLogger->log(
                    event: 'login_blocked',
                    module: 'authentication',
                    auditable: $user,
                    newValues: [
                        'status' => $user->status->value,
                    ],
                    request: $this,
                    actorId: $user->getKey(),
                );
            }

            if ($user?->status === AccountStatus::Suspended) {
                $notificationService->notifySuspendedLoginAttempt(
                    $user,
                    $this->ip(),
                );
            }

            Auth::guard('web')->logout();

            RateLimiter::hit(
                $this->throttleKey(),
                $this->decaySeconds(),
            );

            throw ValidationException::withMessages([
                'login' => match ($user?->status) {
                    AccountStatus::PendingActivation => 'Akun belum diaktifkan. Periksa email aktivasi Anda.',
                    AccountStatus::Suspended => 'Akun sedang ditangguhkan. Hubungi administrator.',
                    default => 'Akun tidak dapat digunakan.',
                },
            ]);
        }

        RateLimiter::clear(
            $this->throttleKey(),
        );

        return $user;
    }

    public function ensureIsNotRateLimited(
        AuditLogger $auditLogger,
        NotificationService $notificationService,
    ): void {
        if (! RateLimiter::tooManyAttempts(
            $this->throttleKey(),
            $this->maxAttempts(),
        )) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn(
            $this->throttleKey(),
        );

        $auditLogger->log(
            event: 'login_rate_limited',
            module: 'authentication',
            newValues: [
                'identifier' => $this
                    ->normalizedLogin(),
                'retry_after_seconds' => $seconds,
            ],
            request: $this,
        );

        $notificationService->notifyRateLimitedLogin(
            $this->normalizedLogin(),
            $this->ip(),
            $seconds,
        );

        throw ValidationException::withMessages([
            'login' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    public function throttleKey(): string
    {
        return 'login:'.hash(
            'sha256',
            $this->normalizedLogin()
                .'|'
                .$this->ip(),
        );
    }

    protected function prepareForValidation(): void
    {
        $login = trim(
            $this->string('login')->toString(),
        );

        $this->merge([
            'login' => filter_var(
                $login,
                FILTER_VALIDATE_EMAIL,
            ) !== false
                ? Str::lower($login)
                : Str::upper($login),
        ]);
    }

    private function loginField(): string
    {
        return filter_var(
            $this->normalizedLogin(),
            FILTER_VALIDATE_EMAIL,
        ) !== false
            ? 'email'
            : 'employee_number';
    }

    private function normalizedLogin(): string
    {
        return $this
            ->string('login')
            ->toString();
    }

    private function maxAttempts(): int
    {
        return (int) config(
            'simantap.security.login_max_attempts',
            5,
        );
    }

    private function decaySeconds(): int
    {
        return (int) config(
            'simantap.security.login_decay_seconds',
            60,
        );
    }
}

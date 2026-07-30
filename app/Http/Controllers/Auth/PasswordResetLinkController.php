<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(
        ForgotPasswordRequest $request,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $email = $request->string('email')->toString();

        $user = User::query()
            ->where('email', $email)
            ->where(
                'status',
                AccountStatus::Active->value,
            )
            ->first();

        if ($user !== null) {
            $status = Password::broker()->sendResetLink(
                ['email' => $user->email],
                static function (
                    User $notifiable,
                    string $token,
                ): void {
                    $notifiable->notify(
                        new ResetPasswordNotification(
                            $token,
                        ),
                    );
                },
            );

            $auditLogger->log(
                event: $status === Password::RESET_LINK_SENT
                    ? 'password_reset_link_sent'
                    : 'password_reset_link_throttled',
                module: 'authentication',
                auditable: $user,
                request: $request,
                actorId: $user->getKey(),
            );
        }

        return back()->with(
            'status',
            'Jika email terdaftar pada akun aktif, '
            .'tautan reset telah dikirim.',
        );
    }
}

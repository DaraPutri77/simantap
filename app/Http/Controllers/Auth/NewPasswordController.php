<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\AccountPasswordService;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(
        Request $request,
        string $token,
    ): View {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(
        ResetPasswordRequest $request,
        AccountPasswordService $passwordService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $status = Password::broker()->reset(
            $request->only(
                'email',
                'password',
                'password_confirmation',
                'token',
            ),
            function (
                User $user,
                string $password,
            ) use (
                $request,
                $passwordService,
                $auditLogger,
            ): void {
                if (
                    $user->status !== AccountStatus::Active
                ) {
                    throw ValidationException::withMessages([
                        'email' => 'Reset kata sandi hanya tersedia '
                            .'untuk akun aktif.',
                    ]);
                }

                $updatedUser = $passwordService->replace(
                    $user,
                    $password,
                );

                event(new PasswordReset($updatedUser));

                $auditLogger->log(
                    event: 'password_reset_completed',
                    module: 'authentication',
                    auditable: $updatedUser,
                    newValues: [
                        'password_changed_at' => $updatedUser
                            ->password_changed_at
                            ?->toIso8601String(),
                    ],
                    request: $request,
                    actorId: $updatedUser->getKey(),
                );
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'Tautan reset kata sandi tidak valid '
                    .'atau sudah kedaluwarsa.',
            ]);
        }

        return redirect()->route('login')->with(
            'status',
            'Kata sandi berhasil direset. Silakan masuk.',
        );
    }
}

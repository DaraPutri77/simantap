<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Services\AccountPasswordService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(
        UpdatePasswordRequest $request,
        AccountPasswordService $passwordService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $user = $request->user();
        $wasMandatory = $user->must_change_password;

        $updatedUser = $passwordService->replace(
            $user,
            $request->string('password')->toString(),
            $request->session()->getId(),
        );

        $request->session()->regenerate(true);

        $auditLogger->log(
            event: $wasMandatory
                ? 'mandatory_password_change_completed'
                : 'password_changed',
            module: 'authentication',
            auditable: $updatedUser,
            newValues: [
                'must_change_password' => false,
                'password_changed_at' => $updatedUser
                    ->password_changed_at
                    ?->toIso8601String(),
            ],
            request: $request,
        );

        return redirect()->route('dashboard')
            ->with(
                'status',
                'Kata sandi berhasil diperbarui.',
            );
    }
}

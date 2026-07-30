<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(
        LoginRequest $request,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $user = $request->authenticate($auditLogger);

        $request->session()->regenerate();

        $previousLoginAt = $user->last_login_at
            ?->toIso8601String();

        $user->forceFill([
            'last_login_at' => now(),
        ])->saveQuietly();

        $auditLogger->log(
            event: 'login_succeeded',
            module: 'authentication',
            auditable: $user,
            oldValues: [
                'last_login_at' => $previousLoginAt,
            ],
            newValues: [
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            request: $request,
        );

        if ($user->must_change_password) {
            return redirect()->route('password.change')
                ->with(
                    'warning',
                    'Ubah kata sandi sementara sebelum menggunakan SIMANTAP.',
                );
        }

        return redirect()->intended(
            route('dashboard'),
        );
    }

    public function destroy(
        Request $request,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $user = $request->user();

        if ($user !== null) {
            $auditLogger->log(
                event: 'logout_succeeded',
                module: 'authentication',
                auditable: $user,
                request: $request,
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

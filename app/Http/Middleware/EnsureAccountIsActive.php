<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $user = $request->user();

        if (
            $user === null
            || $user->status !== AccountStatus::Active
        ) {
            if ($user !== null) {
                $this->auditLogger->log(
                    event: 'inactive_account_session_terminated',
                    module: 'authentication',
                    auditable: $user,
                    newValues: [
                        'status' => $user->status->value,
                    ],
                    request: $request,
                    actorId: $user->getKey(),
                );
            }

            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors([
                    'login' => 'Sesi dihentikan karena akun '
                        .'tidak aktif.',
                ]);
        }

        return $next($request);
    }
}

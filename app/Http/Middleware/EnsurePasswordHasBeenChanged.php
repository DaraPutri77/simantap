<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordHasBeenChanged
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        if (
            $request->user()?->must_change_password === true
            && ! $request->routeIs(
                'password.change',
                'password.update',
                'logout',
            )
        ) {
            return redirect()->route('password.change')
                ->with(
                    'warning',
                    'Ubah kata sandi sementara sebelum melanjutkan.',
                );
        }

        return $next($request);
    }
}

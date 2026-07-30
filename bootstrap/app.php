<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePasswordHasBeenChanged;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(
    basePath: dirname(__DIR__),
)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(
        function (Middleware $middleware): void {
            $middleware->redirectGuestsTo(
                fn (Request $request): string => route(
                    'login',
                ),
            );

            $middleware->redirectUsersTo(
                fn (Request $request): string => $request
                    ->user()
                    ?->must_change_password === true
                        ? route('password.change')
                        : route('dashboard'),
            );

            $middleware->alias([
                'active' => EnsureAccountIsActive::class,
                'password.changed' => EnsurePasswordHasBeenChanged::class,
                'role' => RoleMiddleware::class,
                'permission' => PermissionMiddleware::class,
                'role_or_permission' => RoleOrPermissionMiddleware::class,
            ]);

            $middleware->validateCsrfTokens(
                except: [],
            );
        },
    )
    ->withExceptions(
        function (Exceptions $exceptions): void {
            //
        },
    )
    ->create();

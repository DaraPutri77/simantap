<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


class RoleMiddleware
{

    public function handle(Request $request, Closure $next, $role): Response
    {

        if (!auth()->check()) {

            abort(401);

        }


        $user = auth()->user();


        if (!$user->roles()->where('nama_role', $role)->exists()) {

            abort(403);

        }


        return $next($request);

    }

}
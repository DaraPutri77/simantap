<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ActivateAccountRequest;
use App\Services\AccountActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ActivationController extends Controller
{
    public function show(
        string $token,
        AccountActivationService $activationService,
    ): View {
        return view('auth.activate-account', [
            'token' => $token,
            'user' => $activationService->userForValidToken(
                $token,
            ),
        ]);
    }

    public function store(
        ActivateAccountRequest $request,
        AccountActivationService $activationService,
    ): RedirectResponse {
        $activationService->activate(
            $request->string('token')->toString(),
            $request->string('password')->toString(),
            $request,
        );

        return redirect()->route('login')
            ->with(
                'status',
                'Akun berhasil diaktifkan. Silakan masuk.',
            );
    }
}

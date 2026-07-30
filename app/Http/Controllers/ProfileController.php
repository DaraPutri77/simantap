<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile.show', [
            'user' => $request->user(),
        ]);
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(
        UpdateProfileRequest $request,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $user = $request->user();

        abort_if($user === null, 401);

        $oldValues = $user->only([
            'name',
            'email',
            'phone',
            'email_verified_at',
        ]);

        $user->fill($request->safe()->only([
            'name',
            'email',
            'phone',
        ]));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $changedAttributes = array_keys($user->getDirty());

        if ($changedAttributes === []) {
            return redirect()
                ->route('profile.show')
                ->with('status', 'Tidak ada perubahan profil yang disimpan.');
        }

        $user->save();

        $auditLogger->log(
            event: 'profile_updated',
            module: 'profile',
            auditable: $user,
            oldValues: collect($oldValues)
                ->only($changedAttributes)
                ->all(),
            newValues: $user->only($changedAttributes),
            request: $request,
        );

        return redirect()
            ->route('profile.show')
            ->with('status', 'Profil berhasil diperbarui.');
    }
}

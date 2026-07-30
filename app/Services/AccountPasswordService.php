<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountPasswordService
{
    public function replace(
        User $user,
        #[\SensitiveParameter] string $password,
        ?string $preservedSessionId = null,
    ): User {
        return DB::transaction(function () use (
            $user,
            $password,
            $preservedSessionId,
        ): User {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUser->forceFill([
                'password' => $password,
                'must_change_password' => false,
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            $this->revokeDatabaseSessions(
                $lockedUser,
                $preservedSessionId,
            );

            return $lockedUser;
        }, 3);
    }

    public function revokeDatabaseSessions(
        User $user,
        ?string $preservedSessionId = null,
    ): void {
        if (config('session.driver') !== 'database') {
            return;
        }

        $table = (string) config('session.table', 'sessions');

        $query = DB::table($table)
            ->where('user_id', $user->getKey());

        if ($preservedSessionId !== null) {
            $query->where('id', '!=', $preservedSessionId);
        }

        $query->delete();
    }
}

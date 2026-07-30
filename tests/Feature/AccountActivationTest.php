<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\AccountActivationToken;
use App\Models\User;
use App\Notifications\ActivateAccountNotification;
use App\Services\AccountActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_link_uses_hashed_single_use_token(): void
    {
        Notification::fake();

        $creator = User::factory()->create();
        $user = $this->pendingUser();

        app(AccountActivationService::class)
            ->sendActivationLink(
                $user,
                $creator,
            );

        $plainToken = null;

        Notification::assertSentTo(
            $user,
            ActivateAccountNotification::class,
            function (
                ActivateAccountNotification $notification,
            ) use (&$plainToken): bool {
                $plainToken = $notification->token;

                return true;
            },
        );

        $this->assertNotNull($plainToken);

        $storedToken = AccountActivationToken::query()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertNotSame(
            $plainToken,
            $storedToken->token_hash,
        );

        $this->assertSame(
            hash('sha256', $plainToken),
            $storedToken->getRawOriginal('token_hash'),
        );

        $this->assertSame(
            $creator->id,
            $storedToken->created_by,
        );

        $this->assertNull($storedToken->used_at);

        $this->get(
            route('activation.show', $plainToken),
        )
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee($user->email);

        $response = $this->post(
            route('activation.store'),
            [
                'token' => $plainToken,
                'password' => 'PrivatePassword!456',
                'password_confirmation' => 'PrivatePassword!456',
            ],
        );

        $response->assertRedirect(route('login'));

        $activatedUser = $user->fresh();

        $this->assertSame(
            AccountStatus::Active,
            $activatedUser->status,
        );

        $this->assertNotNull(
            $activatedUser->activated_at,
        );

        $this->assertNotNull(
            $activatedUser->email_verified_at,
        );

        $this->assertNotNull(
            $activatedUser->password_changed_at,
        );

        $this->assertFalse(
            $activatedUser->must_change_password,
        );

        $this->assertTrue(
            Hash::check(
                'PrivatePassword!456',
                $activatedUser->password,
            ),
        );

        $this->assertNotNull(
            $storedToken->fresh()->used_at,
        );

        $this->post(
            route('activation.store'),
            [
                'token' => $plainToken,
                'password' => 'AnotherPassword!789',
                'password_confirmation' => 'AnotherPassword!789',
            ],
        )->assertSessionHasErrors('token');

        $this->assertTrue(
            Hash::check(
                'PrivatePassword!456',
                $activatedUser->fresh()->password,
            ),
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'event' => 'account_activated',
            'module' => 'authentication',
        ]);
    }

    public function test_resending_activation_invalidates_previous_token(): void
    {
        $user = $this->pendingUser();
        $service = app(
            AccountActivationService::class,
        );

        $firstToken = $service->issueToken($user);
        $secondToken = $service->issueToken($user);

        $this->assertNotSame(
            $firstToken,
            $secondToken,
        );

        $this->assertDatabaseCount(
            'account_activation_tokens',
            1,
        );

        $this->get(
            route('activation.show', $firstToken),
        )
            ->assertOk()
            ->assertSee(
                'Tautan tidak dapat digunakan',
            );

        $this->get(
            route('activation.show', $secondToken),
        )
            ->assertOk()
            ->assertSee($user->email);
    }

    public function test_expired_activation_token_cannot_be_used(): void
    {
        $user = $this->pendingUser();

        $token = app(
            AccountActivationService::class,
        )->issueToken($user);

        AccountActivationToken::query()
            ->where('user_id', $user->id)
            ->update([
                'expires_at' => now()->subMinute(),
            ]);

        $this->post(
            route('activation.store'),
            [
                'token' => $token,
                'password' => 'PrivatePassword!456',
                'password_confirmation' => 'PrivatePassword!456',
            ],
        )->assertSessionHasErrors('token');

        $this->assertSame(
            AccountStatus::PendingActivation,
            $user->fresh()->status,
        );
    }

    private function pendingUser(): User
    {
        return User::factory()->create([
            'status' => AccountStatus::PendingActivation,
            'password' => null,
            'must_change_password' => false,
            'email_verified_at' => null,
            'activated_at' => null,
            'password_changed_at' => null,
        ]);
    }
}

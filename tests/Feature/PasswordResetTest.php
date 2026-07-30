<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_request_and_complete_password_reset(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'pegawai@example.test',
            'password' => 'OldPassword!123',
            'status' => AccountStatus::Active,
            'must_change_password' => true,
        ]);

        $response = $this->post(
            route('password.email'),
            [
                'email' => 'PEGAWAI@EXAMPLE.TEST',
            ],
        );

        $response->assertSessionHas(
            'status',
            'Jika email terdaftar pada akun aktif, '
            .'tautan reset telah dikirim.',
        );

        $token = null;

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (
                ResetPasswordNotification $notification,
            ) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        $this->assertNotNull($token);

        $resetResponse = $this->post(
            route('password.store'),
            [
                'token' => $token,
                'email' => $user->email,
                'password' => 'NewPassword!456',
                'password_confirmation' => 'NewPassword!456',
            ],
        );

        $resetResponse->assertRedirect(route('login'));

        $updatedUser = $user->fresh();

        $this->assertTrue(
            Hash::check(
                'NewPassword!456',
                $updatedUser->password,
            ),
        );

        $this->assertFalse(
            $updatedUser->must_change_password,
        );

        $this->assertNotNull(
            $updatedUser->password_changed_at,
        );

        $this->assertDatabaseMissing(
            'password_reset_tokens',
            [
                'email' => $user->email,
            ],
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'event' => 'password_reset_completed',
        ]);
    }

    public function test_forgot_password_response_does_not_reveal_account(): void
    {
        Notification::fake();

        $statusMessage = 'Jika email terdaftar pada akun aktif, '
            .'tautan reset telah dikirim.';

        $unknownResponse = $this->post(
            route('password.email'),
            [
                'email' => 'unknown@example.test',
            ],
        );

        $pendingUser = User::factory()->create([
            'email' => 'pending@example.test',
            'status' => AccountStatus::PendingActivation,
            'password' => null,
        ]);

        $pendingResponse = $this->post(
            route('password.email'),
            [
                'email' => $pendingUser->email,
            ],
        );

        $unknownResponse->assertSessionHas(
            'status',
            $statusMessage,
        );

        $pendingResponse->assertSessionHas(
            'status',
            $statusMessage,
        );

        Notification::assertNothingSent();
    }

    public function test_suspended_account_cannot_reset_password(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.test',
            'password' => 'OldPassword!123',
            'status' => AccountStatus::Suspended,
        ]);

        $token = Password::broker()
            ->createToken($user);

        $this->post(
            route('password.store'),
            [
                'token' => $token,
                'email' => $user->email,
                'password' => 'NewPassword!456',
                'password_confirmation' => 'NewPassword!456',
            ],
        )->assertSessionHasErrors('email');

        $this->assertTrue(
            Hash::check(
                'OldPassword!123',
                $user->fresh()->password,
            ),
        );
    }

    public function test_invalid_reset_token_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'pegawai@example.test',
            'password' => 'OldPassword!123',
            'status' => AccountStatus::Active,
        ]);

        $this->post(
            route('password.store'),
            [
                'token' => 'invalid-reset-token',
                'email' => $user->email,
                'password' => 'NewPassword!456',
                'password_confirmation' => 'NewPassword!456',
            ],
        )->assertSessionHasErrors('email');

        $this->assertTrue(
            Hash::check(
                'OldPassword!123',
                $user->fresh()->password,
            ),
        );
    }
}

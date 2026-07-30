<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_active_user_can_login_with_email(): void
    {
        $user = $this->activeEmployee([
            'email' => 'pegawai@example.test',
            'password' => 'CurrentPassword!123',
        ]);

        $response = $this->post(
            route('login.store'),
            [
                'login' => 'PEGAWAI@EXAMPLE.TEST',
                'password' => 'CurrentPassword!123',
            ],
        );

        $response->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->assertNotNull(
            $user->fresh()->last_login_at,
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'event' => 'login_succeeded',
            'module' => 'authentication',
        ]);
    }

    public function test_active_user_can_login_with_employee_number(): void
    {
        $user = $this->activeEmployee([
            'employee_number' => '198901012026071001',
            'password' => 'CurrentPassword!123',
        ]);

        $response = $this->post(
            route('login.store'),
            [
                'login' => '198901012026071001',
                'password' => 'CurrentPassword!123',
            ],
        );

        $response->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected_and_audited(): void
    {
        $response = $this->from(route('login'))->post(
            route('login.store'),
            [
                'login' => 'unknown@example.test',
                'password' => 'WrongPassword!123',
            ],
        );

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('login');

        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => null,
            'event' => 'login_failed',
            'module' => 'authentication',
        ]);
    }

    public function test_pending_and_suspended_accounts_cannot_login(): void
    {
        $pending = User::factory()->create([
            'email' => 'pending@example.test',
            'password' => 'CurrentPassword!123',
            'status' => AccountStatus::PendingActivation,
            'email_verified_at' => null,
            'activated_at' => null,
        ]);

        $suspended = User::factory()->create([
            'email' => 'suspended@example.test',
            'password' => 'CurrentPassword!123',
            'status' => AccountStatus::Suspended,
        ]);

        $this->from(route('login'))->post(
            route('login.store'),
            [
                'login' => $pending->email,
                'password' => 'CurrentPassword!123',
            ],
        )->assertSessionHasErrors('login');

        $this->assertGuest();

        $this->from(route('login'))->post(
            route('login.store'),
            [
                'login' => $suspended->email,
                'password' => 'CurrentPassword!123',
            ],
        )->assertSessionHasErrors('login');

        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $pending->id,
            'event' => 'login_blocked',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $suspended->id,
            'event' => 'login_blocked',
        ]);
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        config()->set(
            'simantap.security.login_max_attempts',
            2,
        );

        $payload = [
            'login' => 'limited@example.test',
            'password' => 'WrongPassword!123',
        ];

        $this->from(route('login'))
            ->post(route('login.store'), $payload)
            ->assertSessionHasErrors('login');

        $this->from(route('login'))
            ->post(route('login.store'), $payload)
            ->assertSessionHasErrors('login');

        $response = $this->from(route('login'))
            ->post(route('login.store'), $payload);

        $response->assertSessionHasErrors('login');

        $this->assertStringContainsString(
            'Terlalu banyak percobaan login',
            $response->getSession()
                ->get('errors')
                ->first('login'),
        );

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_rate_limited',
            'module' => 'authentication',
        ]);
    }

    public function test_user_with_temporary_password_must_change_it(): void
    {
        $user = $this->activeEmployee([
            'password' => 'TemporaryPassword!123',
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);

        $this->post(
            route('login.store'),
            [
                'login' => $user->email,
                'password' => 'TemporaryPassword!123',
            ],
        )->assertRedirect(route('password.change'));

        $this->get(route('dashboard'))
            ->assertRedirect(route('password.change'));

        $response = $this->put(
            route('password.update'),
            [
                'current_password' => 'TemporaryPassword!123',
                'password' => 'PrivatePassword!456',
                'password_confirmation' => 'PrivatePassword!456',
            ],
        );

        $response->assertRedirect(route('dashboard'));

        $updatedUser = $user->fresh();

        $this->assertFalse(
            $updatedUser->must_change_password,
        );

        $this->assertNotNull(
            $updatedUser->password_changed_at,
        );

        $this->assertTrue(
            Hash::check(
                'PrivatePassword!456',
                $updatedUser->password,
            ),
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'event' => 'mandatory_password_change_completed',
        ]);
    }

    public function test_wrong_current_password_cannot_change_password(): void
    {
        $user = $this->activeEmployee([
            'password' => 'CurrentPassword!123',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->from(route('password.change'))
            ->put(
                route('password.update'),
                [
                    'current_password' => 'IncorrectPassword!123',
                    'password' => 'PrivatePassword!456',
                    'password_confirmation' => 'PrivatePassword!456',
                ],
            )
            ->assertSessionHasErrors(
                'current_password',
            );

        $this->assertTrue(
            Hash::check(
                'CurrentPassword!123',
                $user->fresh()->password,
            ),
        );
    }

    public function test_logout_invalidates_authenticated_session(): void
    {
        $user = $this->activeEmployee();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'event' => 'logout_succeeded',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function activeEmployee(
        array $attributes = [],
    ): User {
        $user = User::factory()->create([
            'status' => AccountStatus::Active,
            'must_change_password' => false,
            ...$attributes,
        ]);

        $user->assignRole(
            RoleName::Employee->value,
        );

        return $user;
    }
}

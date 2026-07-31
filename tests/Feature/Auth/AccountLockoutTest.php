<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Core\Auth\Services\AccountLockoutService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountLockoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.auth.email_verification.required' => false,
            'corepanel.auth.lockout.enabled' => true,
            'corepanel.auth.lockout.max_attempts' => 3,
            'corepanel.auth.lockout.lockout_minutes' => null,
            'corepanel.auth.login.max_attempts' => 100,
            'corepanel.password.check_compromised' => false,
            'session.driver' => 'array',
        ]);
    }

    public function test_account_locks_after_max_failed_login_attempts(): void
    {
        User::factory()->create([
            'email' => 'lockout@corepanel.test',
            'password' => 'password',
        ]);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post('/login', [
                'email' => 'lockout@corepanel.test',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->assertDatabaseHas('users', [
            'email' => 'lockout@corepanel.test',
            'status' => 'locked',
            'failed_login_attempts' => 3,
        ]);

        $this->assertNotNull(User::query()->where('email', 'lockout@corepanel.test')->value('locked_at'));
    }

    public function test_locked_user_cannot_login_even_with_correct_password(): void
    {
        User::factory()->locked()->create([
            'email' => 'locked@corepanel.test',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'locked@corepanel.test',
            'password' => 'password',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_successful_login_clears_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'clear@corepanel.test',
            'password' => 'password',
            'failed_login_attempts' => 2,
        ]);

        $this->post('/login', [
            'email' => 'clear@corepanel.test',
            'password' => 'password',
        ])->assertRedirect('/');

        $user->refresh();

        $this->assertSame('active', $user->status);
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_at);
    }

    public function test_password_reset_unlocks_locked_account(): void
    {
        $user = User::factory()->locked()->create([
            'email' => 'reset-unlock@corepanel.test',
        ]);

        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'email' => 'reset-unlock@corepanel.test',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
            'token' => $token,
        ])->assertRedirect(route('login'));

        $user->refresh();

        $this->assertSame('active', $user->status);
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_at);
    }

    public function test_locked_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        User::factory()->locked()->create([
            'email' => 'locked-reset@corepanel.test',
        ]);

        $this->post(route('password.email'), [
            'email' => 'locked-reset@corepanel.test',
        ])->assertRedirect();
    }

    public function test_email_verification_unlocks_locked_account(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        config(['corepanel.auth.email_verification.required' => true]);

        $user = User::factory()->unverified()->locked()->create([
            'email' => 'verify-unlock@corepanel.test',
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $this->actingAs($user)
            ->get($verificationUrl)
            ->assertRedirect('/')
            ->assertSessionHas('status');

        $user->refresh();

        $this->assertSame('active', $user->status);
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_at);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_account_auto_unlocks_after_lockout_period(): void
    {
        config(['corepanel.auth.lockout.lockout_minutes' => 30]);

        $user = User::factory()->locked()->create([
            'email' => 'auto-unlock@corepanel.test',
            'password' => 'password',
            'locked_at' => now()->subMinutes(31),
        ]);

        $this->post('/login', [
            'email' => 'auto-unlock@corepanel.test',
            'password' => 'password',
        ])->assertRedirect('/');

        $user->refresh();

        $this->assertSame('active', $user->status);
        $this->assertAuthenticatedAs($user);
    }

    public function test_lockout_can_be_disabled_via_config(): void
    {
        config(['corepanel.auth.lockout.enabled' => false]);

        User::factory()->create([
            'email' => 'disabled@corepanel.test',
            'password' => 'password',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => 'disabled@corepanel.test',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->assertDatabaseHas('users', [
            'email' => 'disabled@corepanel.test',
            'status' => 'active',
            'failed_login_attempts' => 0,
        ]);
    }

    public function test_account_lockout_service_unlock_method_restores_active_status(): void
    {
        $user = User::factory()->locked()->create();

        app(AccountLockoutService::class)->unlock($user);

        $user->refresh();

        $this->assertSame('active', $user->status);
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_at);
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Auth\Concerns\InteractsWithAuthSessions;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use InteractsWithAuthSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.auth.email_verification.required' => false,
            'corepanel.auth.audit.enabled' => false,
            'corepanel.auth.lockout.enabled' => false,
            'corepanel.auth.login.max_attempts' => 100,
            'session.driver' => 'array',
        ]);
    }

    public function test_guest_can_view_login_page(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in to your account', false);
    }

    public function test_authenticated_user_is_redirected_from_login_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->from(route('login'))
            ->post(route('login'), [])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@corepanel.test',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'login@corepanel.test',
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_invalid_password_returns_error(): void
    {
        User::factory()->create([
            'email' => 'wrongpass@corepanel.test',
            'password' => 'password',
        ]);

        $this->from(route('login'))
            ->post('/login', [
                'email' => 'wrongpass@corepanel.test',
                'password' => 'not-the-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_suspended_user_cannot_login(): void
    {
        User::factory()->suspended()->create([
            'email' => 'suspended-login@corepanel.test',
            'password' => 'password',
        ]);

        $this->from(route('login'))
            ->post('/login', [
                'email' => 'suspended-login@corepanel.test',
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_successful_login_updates_last_login_at(): void
    {
        $user = User::factory()->create([
            'email' => 'last-login@corepanel.test',
            'password' => 'password',
            'last_login_at' => null,
        ]);

        $this->post('/login', [
            'email' => 'last-login@corepanel.test',
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_user_can_logout(): void
    {
        User::factory()->create([
            'email' => 'logout-flow@corepanel.test',
            'password' => 'password',
        ]);

        $loginResponse = $this->post('/login', [
            'email' => 'logout-flow@corepanel.test',
            'password' => 'password',
        ]);

        $loginResponse->assertRedirect('/');

        $this->persistCookiesFrom($loginResponse);
        $this->assertAuthenticated();

        $this->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}

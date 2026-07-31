<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class GuestRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.auth.registration.mode' => 'open',
            'corepanel.auth.email_verification.required' => false,
            'corepanel.password.check_compromised' => false,
            'session.driver' => 'array',
        ]);
    }

    public function test_guest_can_access_auth_entry_routes(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
        $this->get(route('password.request'))->assertOk();
    }

    public function test_authenticated_user_is_redirected_from_guest_auth_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('login'))->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get(route('register'))->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get(route('password.request'))->assertRedirect(route('dashboard'));
    }

    public function test_authenticated_user_is_redirected_from_password_reset_form(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->actingAs($user)
            ->get(route('password.reset', ['token' => $token]))
            ->assertRedirect(route('dashboard'));
    }

    public function test_unauthenticated_user_cannot_access_verified_account_routes(): void
    {
        $this->get(route('account.sessions.index'))
            ->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_cannot_access_verification_notice(): void
    {
        $this->get(route('verification.notice'))
            ->assertRedirect(route('login'));
    }
}

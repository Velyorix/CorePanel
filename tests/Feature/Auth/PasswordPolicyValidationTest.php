<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordPolicyValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.auth.registration.mode' => 'open',
            'corepanel.auth.email_verification.required' => false,
            'corepanel.password.min_length' => 12,
            'corepanel.password.require_special_character' => true,
            'corepanel.password.check_compromised' => false,
            'session.driver' => 'array',
        ]);
    }

    public function test_registration_rejects_password_that_does_not_meet_policy(): void
    {
        $this->from(route('register'))
            ->post('/register', [
                'name' => 'Weak Password',
                'email' => 'weak@corepanel.test',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', [
            'email' => 'weak@corepanel.test',
        ]);
    }

    public function test_password_reset_rejects_password_that_does_not_meet_policy(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-weak@corepanel.test',
        ]);

        $token = Password::createToken($user);

        $this->from(route('password.reset', ['token' => $token]))
            ->post('/reset-password', [
                'email' => 'reset-weak@corepanel.test',
                'token' => $token,
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertRedirect(route('password.reset', ['token' => $token]))
            ->assertSessionHasErrors('password');
    }
}

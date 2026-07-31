<?php

namespace Tests\Feature\Auth;

use Core\Auth\Notifications\VerifyEmailNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserRegistrationWithVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.password.check_compromised' => false,
            'corepanel.auth.registration.mode' => 'open',
            'corepanel.auth.email_verification.required' => true,
            'session.driver' => 'array',
        ]);
    }

    public function test_open_registration_redirects_to_verification_notice_and_sends_email(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'verified-flow@corepanel.test',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertRedirect(route('verification.notice'));

        $user = \App\Models\User::query()->where('email', 'verified-flow@corepanel.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }
}

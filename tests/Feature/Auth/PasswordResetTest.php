<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Core\Auth\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.password.check_compromised' => false,
        ]);
    }

    public function test_forgot_password_request_sends_notification_for_active_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset@corepanel.test',
        ]);

        $response = $this->post('/forgot-password', [
            'email' => 'reset@corepanel.test',
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_request_does_not_reveal_unknown_email(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', [
            'email' => 'missing@corepanel.test',
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_forgot_password_request_does_not_send_for_suspended_user(): void
    {
        Notification::fake();

        User::factory()->suspended()->create([
            'email' => 'suspended@corepanel.test',
        ]);

        $response = $this->post('/forgot-password', [
            'email' => 'suspended@corepanel.test',
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'newpass@corepanel.test',
        ]);

        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'email' => 'newpass@corepanel.test',
            'token' => $token,
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('NewSecurePass123!', $user->fresh()->password));
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        User::factory()->create([
            'email' => 'invalid@corepanel.test',
        ]);

        $response = $this->post('/reset-password', [
            'email' => 'invalid@corepanel.test',
            'token' => 'invalid-token',
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        Notification::fake();

        config([
            'corepanel.auth.password_reset.max_attempts' => 2,
            'corepanel.auth.password_reset.decay_seconds' => 60,
        ]);

        $this->post('/forgot-password', ['email' => 'missing@corepanel.test'])->assertSessionHas('status');
        $this->post('/forgot-password', ['email' => 'missing@corepanel.test'])->assertSessionHas('status');

        $this->post('/forgot-password', ['email' => 'missing@corepanel.test'])
            ->assertSessionHasErrors('email');
    }
}

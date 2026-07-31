<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Core\Auth\Notifications\VerifyEmailNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.auth.email_verification.required' => true,
            'corepanel.password.check_compromised' => false,
            'session.driver' => 'array',
        ]);
    }

    public function test_unverified_user_is_redirected_from_verified_routes(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_access_verified_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_user_can_verify_email_with_valid_signed_url(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'verify@corepanel.test',
        ]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect('/')
            ->assertSessionHas('status');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_invalid_signed_verification_url_is_rejected(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'invalid@corepanel.test',
        ]);

        $this->actingAs($user)
            ->get('/email/verify/'.$user->id.'/invalid-hash')
            ->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_login_redirects_unverified_user_to_verification_notice(): void
    {
        User::factory()->unverified()->create([
            'email' => 'login-unverified@corepanel.test',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'login-unverified@corepanel.test',
            'password' => 'password',
        ])->assertRedirect(route('verification.notice'));
    }

    public function test_user_can_resend_verification_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post('/email/verification-notification')
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_registration_sends_verification_notification_when_required(): void
    {
        Notification::fake();

        config(['corepanel.auth.registration.mode' => 'open']);

        $this->post('/register', [
            'name' => 'Verify Me',
            'email' => 'register-verify@corepanel.test',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'register-verify@corepanel.test')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
        $this->assertNull($user->email_verified_at);
    }

    public function test_registration_skips_verification_flow_when_disabled(): void
    {
        Notification::fake();

        config([
            'corepanel.auth.registration.mode' => 'open',
            'corepanel.auth.email_verification.required' => false,
            'corepanel.password.check_compromised' => false,
        ]);

        $this->post('/register', [
            'name' => 'No Verify',
            'email' => 'no-verify@corepanel.test',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ])->assertRedirect('/');

        Notification::assertNothingSent();
    }
}

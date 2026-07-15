<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Core\Auth\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UserSessionTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'array',
            'corepanel.auth.session.activity_touch_interval_seconds' => 1,
        ]);
    }

    public function test_successful_login_creates_user_session_record(): void
    {
        $user = User::factory()->create([
            'email' => 'session@corepanel.test',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => 'session@corepanel.test',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');

        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
        ]);

        $userSession = UserSession::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertNotNull($userSession->session_id);
        $this->assertNotNull($userSession->device_name);
        $this->assertNotNull($userSession->last_activity_at);
        $this->assertNotNull($userSession->expires_at);
    }

    public function test_logout_removes_tracked_user_session(): void
    {
        User::factory()->create([
            'email' => 'logout@corepanel.test',
            'password' => 'password',
        ]);

        $loginResponse = $this->post('/login', [
            'email' => 'logout@corepanel.test',
            'password' => 'password',
        ]);

        $loginResponse->assertRedirect('/');

        $this->persistCookiesFrom($loginResponse);
        $this->assertAuthenticated();
        $this->assertDatabaseCount('user_sessions', 1);

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseCount('user_sessions', 0);
    }

    public function test_authenticated_request_updates_last_activity(): void
    {
        User::factory()->create([
            'email' => 'activity@corepanel.test',
            'password' => 'password',
        ]);

        $loginResponse = $this->post('/login', [
            'email' => 'activity@corepanel.test',
            'password' => 'password',
        ]);

        $loginResponse->assertRedirect('/');

        $this->persistCookiesFrom($loginResponse);
        $this->assertAuthenticated();

        $userSession = UserSession::query()->firstOrFail();
        $initialActivity = $userSession->last_activity_at;

        $this->travel(2)->minutes();

        $this->get('/')->assertOk();

        $userSession->refresh();

        $this->assertTrue($userSession->last_activity_at->gt($initialActivity));
    }

    private function persistCookiesFrom(TestResponse $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }
    }
}

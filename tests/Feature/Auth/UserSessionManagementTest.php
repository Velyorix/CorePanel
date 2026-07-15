<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Core\Auth\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSessionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.auth.email_verification.required' => false,
            'session.driver' => 'array',
        ]);
    }

    public function test_authenticated_user_can_list_active_sessions(): void
    {
        $user = User::factory()->create();

        UserSession::factory()->count(2)->create([
            'user_id' => $user->id,
        ]);

        UserSession::factory()->expired()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('account.sessions.index'));

        $response->assertOk();
        $response->assertSee('Active sessions');
        $response->assertSee('Revoke all other sessions', false);
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherSession = UserSession::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($user)
            ->delete(route('account.sessions.destroy', $otherSession))
            ->assertForbidden();

        $this->assertDatabaseHas('user_sessions', [
            'id' => $otherSession->id,
        ]);
    }

    public function test_user_can_revoke_one_of_their_sessions(): void
    {
        $user = User::factory()->create();

        $otherSession = UserSession::factory()->create([
            'user_id' => $user->id,
            'device_name' => 'Other device',
        ]);

        $remainingSession = UserSession::factory()->create([
            'user_id' => $user->id,
            'device_name' => 'Another device',
        ]);

        $this->actingAs($user)
            ->delete(route('account.sessions.destroy', $otherSession))
            ->assertRedirect();

        $this->assertDatabaseMissing('user_sessions', [
            'id' => $otherSession->id,
        ]);

        $this->assertDatabaseHas('user_sessions', [
            'id' => $remainingSession->id,
        ]);
    }

    public function test_user_can_revoke_all_other_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-others@corepanel.test',
            'password' => 'password',
        ]);

        $loginResponse = $this->post('/login', [
            'email' => 'revoke-others@corepanel.test',
            'password' => 'password',
        ]);

        $loginResponse->assertRedirect('/');

        $currentSession = UserSession::query()->where('user_id', $user->id)->firstOrFail();

        UserSession::factory()->create([
            'user_id' => $user->id,
            'session_id' => 'other-session-a',
        ]);

        UserSession::factory()->create([
            'user_id' => $user->id,
            'session_id' => 'other-session-b',
        ]);

        foreach ($loginResponse->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }

        $this->assertAuthenticated();

        $this->post(route('account.sessions.revoke-others'))
            ->assertRedirect();

        $this->assertDatabaseCount('user_sessions', 1);
        $this->assertDatabaseHas('user_sessions', [
            'id' => $currentSession->id,
        ]);
    }

    public function test_revoking_current_session_logs_user_out(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke-self@corepanel.test',
            'password' => 'password',
        ]);

        $loginResponse = $this->post('/login', [
            'email' => 'revoke-self@corepanel.test',
            'password' => 'password',
        ]);

        $loginResponse->assertRedirect('/');

        $userSession = UserSession::query()->where('user_id', $user->id)->firstOrFail();

        foreach ($loginResponse->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }

        $this->assertAuthenticated();

        $this->delete(route('account.sessions.destroy', $userSession))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('user_sessions', [
            'id' => $userSession->id,
        ]);
    }

    public function test_guest_cannot_access_session_management(): void
    {
        $this->get(route('account.sessions.index'))
            ->assertRedirect(route('login'));
    }
}

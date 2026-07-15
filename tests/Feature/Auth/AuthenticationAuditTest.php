<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Core\Auth\Models\User as CoreUser;
use Core\Auth\Services\AuthenticationAuditLogger;
use Core\Support\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthenticationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.auth.email_verification.required' => false,
            'corepanel.auth.audit.enabled' => true,
            'corepanel.auth.lockout.enabled' => false,
            'corepanel.auth.login.max_attempts' => 100,
            'session.driver' => 'array',
        ]);
    }

    public function test_successful_login_creates_audit_log(): void
    {
        $user = User::factory()->create([
            'email' => 'audit-login@corepanel.test',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'audit-login@corepanel.test',
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => AuthenticationAuditLogger::ACTION_LOGIN_SUCCESS,
            'entity_type' => CoreUser::class,
            'entity_id' => $user->id,
            'ip_address' => '127.0.0.1',
        ]);

        $log = AuditLog::query()->where('action', AuthenticationAuditLogger::ACTION_LOGIN_SUCCESS)->firstOrFail();

        $this->assertSame('audit-login@corepanel.test', $log->after['email']);
        $this->assertArrayNotHasKey('password', $log->after ?? []);
    }

    public function test_failed_login_with_unknown_email_creates_audit_log_without_actor(): void
    {
        $this->post('/login', [
            'email' => 'missing@corepanel.test',
            'password' => 'wrong-password',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => null,
            'action' => AuthenticationAuditLogger::ACTION_LOGIN_FAILED,
            'entity_type' => null,
            'entity_id' => null,
            'ip_address' => '127.0.0.1',
        ]);

        $log = AuditLog::query()->where('action', AuthenticationAuditLogger::ACTION_LOGIN_FAILED)->firstOrFail();

        $this->assertSame('missing@corepanel.test', $log->after['email']);
        $this->assertSame('invalid_credentials', $log->after['reason']);
    }

    public function test_failed_login_with_wrong_password_creates_audit_log_with_context(): void
    {
        config(['corepanel.auth.lockout.enabled' => true]);

        $user = User::factory()->create([
            'email' => 'audit-fail@corepanel.test',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'audit-fail@corepanel.test',
            'password' => 'wrong-password',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => AuthenticationAuditLogger::ACTION_LOGIN_FAILED,
            'entity_type' => CoreUser::class,
            'entity_id' => $user->id,
        ]);

        $log = AuditLog::query()->where('action', AuthenticationAuditLogger::ACTION_LOGIN_FAILED)->firstOrFail();

        $this->assertSame('invalid_credentials', $log->after['reason']);
        $this->assertSame(1, $log->after['failed_login_attempts']);
        $this->assertFalse($log->after['locked']);
    }

    public function test_locked_account_login_attempt_is_audited(): void
    {
        config(['corepanel.auth.lockout.enabled' => true]);

        $user = User::factory()->locked()->create([
            'email' => 'audit-locked@corepanel.test',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'audit-locked@corepanel.test',
            'password' => 'password',
        ])->assertRedirect();

        $log = AuditLog::query()
            ->where('action', AuthenticationAuditLogger::ACTION_LOGIN_FAILED)
            ->where('entity_id', $user->id)
            ->firstOrFail();

        $this->assertSame('account_locked', $log->after['reason']);
        $this->assertTrue($log->after['locked']);
    }

    public function test_logout_creates_audit_log(): void
    {
        User::factory()->create([
            'email' => 'audit-logout@corepanel.test',
            'password' => 'password',
        ]);

        $loginResponse = $this->post('/login', [
            'email' => 'audit-logout@corepanel.test',
            'password' => 'password',
        ]);

        $loginResponse->assertRedirect('/');

        $user = User::query()->where('email', 'audit-logout@corepanel.test')->firstOrFail();

        $this->persistCookiesFrom($loginResponse);
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => AuthenticationAuditLogger::ACTION_LOGOUT,
            'entity_type' => CoreUser::class,
            'entity_id' => $user->id,
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_authentication_audit_can_be_disabled_via_config(): void
    {
        config(['corepanel.auth.audit.enabled' => false]);

        User::factory()->create([
            'email' => 'audit-disabled@corepanel.test',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'audit-disabled@corepanel.test',
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function persistCookiesFrom(TestResponse $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }
    }
}

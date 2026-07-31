<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Core\API\Models\ApiRequestLog;
use Core\API\Models\ApiToken;
use Core\API\Services\ApiTokenService;
use Core\API\Support\ApiScope;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRequestLoggingTest extends TestCase
{
    use RefreshDatabase;

    private ApiTokenService $tokens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->tokens = app(ApiTokenService::class);

        config([
            'cache.default' => 'array',
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.api-request-log',
            'corepanel.api.rate_limit.enabled' => false,
            'corepanel.api.token_prefix' => 'cpat_',
            'corepanel.api.request_log.enabled' => true,
        ]);
    }

    public function test_authenticated_request_is_logged_with_user_and_token(): void
    {
        $user = User::factory()->create();
        $issued = $this->tokens->issue($user, 'Logging', [ApiScope::ME]);
        /** @var ApiToken $token */
        $token = $issued['token'];

        $this->withToken($issued['plain_text'])
            ->getJson(route('v1.me'), [
                'X-Request-Id' => 'req-log-me-1',
            ])
            ->assertOk();

        $log = ApiRequestLog::query()->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('req-log-me-1', $log->request_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($token->id, $log->api_token_id);
        $this->assertSame('GET', $log->method);
        $this->assertSame('/api/v1/me', $log->path);
        $this->assertSame('v1.me', $log->route_name);
        $this->assertSame(200, $log->status_code);
        $this->assertNull($log->error_code);
        $this->assertSame('v1', $log->api_version);
        $this->assertGreaterThanOrEqual(0, $log->duration_ms);
    }

    public function test_unauthenticated_ping_is_logged_without_actor(): void
    {
        $this->getJson(route('v1.ping'), [
            'X-Request-Id' => 'req-log-ping-1',
        ])->assertOk();

        $log = ApiRequestLog::query()->where('request_id', 'req-log-ping-1')->first();

        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
        $this->assertNull($log->api_token_id);
        $this->assertSame('GET', $log->method);
        $this->assertSame('/api/v1/ping', $log->path);
        $this->assertSame(200, $log->status_code);
    }

    public function test_error_responses_capture_error_code(): void
    {
        $this->getJson(route('v1.me'), [
            'X-Request-Id' => 'req-log-unauth-1',
        ])->assertUnauthorized();

        $log = ApiRequestLog::query()->where('request_id', 'req-log-unauth-1')->first();

        $this->assertNotNull($log);
        $this->assertSame(401, $log->status_code);
        $this->assertSame('unauthenticated', $log->error_code);
    }

    public function test_sensitive_query_params_are_redacted(): void
    {
        $user = User::factory()->create();
        $token = $this->tokens->issue($user, 'Logging', [ApiScope::ME])['plain_text'];

        $this->withToken($token)
            ->getJson(route('v1.me', [
                'token' => 'super-secret',
                'page' => 2,
            ]), [
                'X-Request-Id' => 'req-log-redact-1',
            ])
            ->assertOk();

        $log = ApiRequestLog::query()->where('request_id', 'req-log-redact-1')->first();

        $this->assertNotNull($log);
        $this->assertSame('[redacted]', $log->query['token'] ?? null);
        $this->assertSame(2, (int) ($log->query['page'] ?? 0));
    }

    public function test_logging_can_be_disabled(): void
    {
        config(['corepanel.api.request_log.enabled' => false]);

        $this->getJson(route('v1.ping'), [
            'X-Request-Id' => 'req-log-disabled-1',
        ])->assertOk();

        $this->assertDatabaseMissing('api_request_logs', [
            'request_id' => 'req-log-disabled-1',
        ]);
    }
}

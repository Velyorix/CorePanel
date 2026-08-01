<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Core\API\Services\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'corepanel.api.rate_limit.enabled' => true,
            'corepanel.api.rate_limit.max_attempts' => 3,
            'corepanel.api.rate_limit.decay_seconds' => 60,
            'corepanel.api.token_prefix' => 'cpat_',
        ]);

        Cache::flush();
    }

    public function test_disabled_rate_limit_does_not_add_headers(): void
    {
        config(['corepanel.api.rate_limit.enabled' => false]);

        $this->getJson(route('v1.ping'))
            ->assertOk()
            ->assertHeaderMissing('X-RateLimit-Limit');
    }

    public function test_successful_requests_include_rate_limit_headers(): void
    {
        $first = $this->getJson(route('v1.ping'));

        $first
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '3')
            ->assertHeader('X-RateLimit-Remaining', '2');

        $this->assertNotEmpty($first->headers->get('X-RateLimit-Reset'));

        $this->getJson(route('v1.ping'))
            ->assertOk()
            ->assertHeader('X-RateLimit-Remaining', '1');

        $this->getJson(route('v1.ping'))
            ->assertOk()
            ->assertHeader('X-RateLimit-Remaining', '0');
    }

    public function test_exceeding_limit_returns_429_envelope_and_headers(): void
    {
        $this->getJson(route('v1.ping'))->assertOk();
        $this->getJson(route('v1.ping'))->assertOk();
        $this->getJson(route('v1.ping'))->assertOk();

        $response = $this->getJson(route('v1.ping'));

        $response
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limit_exceeded')
            ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['retry_after']]])
            ->assertHeader('X-RateLimit-Limit', '3')
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertHeader('Retry-After')
            ->assertHeader('X-Api-Version', 'v1');

        $this->assertGreaterThan(0, (int) $response->json('error.details.retry_after'));
    }

    public function test_different_ips_have_separate_buckets(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->getJson(route('v1.ping'))
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->getJson(route('v1.ping'))
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->getJson(route('v1.ping'))
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->getJson(route('v1.ping'))
            ->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->getJson(route('v1.ping'))
            ->assertOk()
            ->assertHeader('X-RateLimit-Remaining', '2');
    }

    public function test_different_tokens_have_separate_buckets_on_me(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $tokenA = app(ApiTokenService::class)->issue($userA, 'A')['plain_text'];
        $tokenB = app(ApiTokenService::class)->issue($userB, 'B')['plain_text'];

        foreach (range(1, 3) as $_) {
            $this->withToken($tokenA)
                ->getJson(route('v1.me'))
                ->assertOk();
        }

        $this->withToken($tokenA)
            ->getJson(route('v1.me'))
            ->assertStatus(429);

        $this->withToken($tokenB)
            ->getJson(route('v1.me'))
            ->assertOk()
            ->assertHeader('X-RateLimit-Remaining', '2');
    }

    public function test_ping_and_me_use_separate_route_buckets(): void
    {
        $user = User::factory()->create();
        $token = app(ApiTokenService::class)->issue($user, 'Route buckets')['plain_text'];

        foreach (range(1, 3) as $_) {
            $this->withToken($token)
                ->getJson(route('v1.ping'))
                ->assertOk();
        }

        $this->withToken($token)
            ->getJson(route('v1.ping'))
            ->assertStatus(429);

        $this->withToken($token)
            ->getJson(route('v1.me'))
            ->assertOk()
            ->assertHeader('X-RateLimit-Remaining', '2');
    }
}

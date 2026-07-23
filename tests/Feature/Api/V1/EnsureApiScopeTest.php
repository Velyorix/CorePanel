<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Core\API\Support\ApiScope;
use Core\API\Services\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\TestCase;

class EnsureApiScopeTest extends TestCase
{
    use RefreshDatabase;

    private ApiTokenService $tokens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokens = app(ApiTokenService::class);

        config([
            'corepanel.api.token_prefix' => 'cpat_',
            'corepanel.api.token_entropy_length' => 40,
        ]);

        Route::middleware(['api', 'api.v1', 'api.auth', 'api.scope:'.ApiScope::SERVICE_READ.','.ApiScope::SERVICE_WRITE])
            ->get('/api/v1/_scope-probe', fn () => response()->json(['ok' => true]));
    }

    public function test_unrestricted_token_can_access_me(): void
    {
        $user = User::factory()->create();
        $issued = $this->tokens->issue($user, 'Full');

        $this->withToken($issued['plain_text'])
            ->getJson(route('v1.me'))
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('scopes.0', ApiScope::ALL);
    }

    public function test_wildcard_token_can_access_me(): void
    {
        $user = User::factory()->create();
        $issued = $this->tokens->issue($user, 'Star', [ApiScope::ALL]);

        $this->withToken($issued['plain_text'])
            ->getJson(route('v1.me'))
            ->assertOk()
            ->assertJsonPath('scopes.0', ApiScope::ALL);
    }

    public function test_token_with_api_me_scope_can_access_me(): void
    {
        $user = User::factory()->create();
        $issued = $this->tokens->issue($user, 'Me only', [ApiScope::ME]);

        $this->withToken($issued['plain_text'])
            ->getJson(route('v1.me'))
            ->assertOk()
            ->assertJsonPath('scopes.0', ApiScope::ME);
    }

    public function test_token_without_api_me_is_forbidden(): void
    {
        $user = User::factory()->create();
        $issued = $this->tokens->issue($user, 'Services', [ApiScope::SERVICE_READ]);

        $this->withToken($issued['plain_text'])
            ->getJson(route('v1.me'))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'insufficient_scope')
            ->assertJsonPath('error.details.required_scopes', [ApiScope::ME])
            ->assertHeader('X-Api-Version', 'v1');
    }

    public function test_empty_permissions_token_is_forbidden_on_scoped_route(): void
    {
        $user = User::factory()->create();
        $issued = $this->tokens->issue($user, 'Empty', []);

        $this->withToken($issued['plain_text'])
            ->getJson(route('v1.me'))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'insufficient_scope');
    }

    public function test_ping_remains_public(): void
    {
        $this->getJson(route('v1.ping'))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_and_scopes_require_all_listed_scopes(): void
    {
        $user = User::factory()->create();

        $readOnly = $this->tokens->issue($user, 'Read', [ApiScope::SERVICE_READ]);
        $this->withToken($readOnly['plain_text'])
            ->getJson('/api/v1/_scope-probe')
            ->assertForbidden();

        $readWrite = $this->tokens->issue($user, 'RW', [
            ApiScope::SERVICE_READ,
            ApiScope::SERVICE_WRITE,
        ]);
        $this->withToken($readWrite['plain_text'])
            ->getJson('/api/v1/_scope-probe')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_resource_wildcard_satisfies_specific_scope(): void
    {
        $user = User::factory()->create();
        $issued = $this->tokens->issue($user, 'Wildcard', [
            ApiScope::ME,
            'api.service.*',
        ]);

        $this->withToken($issued['plain_text'])
            ->getJson('/api/v1/_scope-probe')
            ->assertOk();
    }

    public function test_issue_rejects_unknown_scope(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->tokens->issue($user, 'Bad', ['api.unknown.scope']);
    }

    public function test_issue_rejects_wildcard_combined_with_other_scopes(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->tokens->issue($user, 'Bad', [ApiScope::ALL, ApiScope::ME]);
    }
}

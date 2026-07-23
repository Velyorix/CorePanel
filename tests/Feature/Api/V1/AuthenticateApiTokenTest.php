<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Core\API\Services\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticateApiTokenTest extends TestCase
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
    }

    public function test_ping_remains_public_without_token(): void
    {
        $this->getJson(route('v1.ping'))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_me_requires_bearer_token(): void
    {
        $this->getJson(route('v1.me'))
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertHeader('X-Api-Version', 'v1');

        $this->assertNotEmpty(
            $this->getJson(route('v1.me'))->headers->get('X-Request-Id'),
        );
    }

    public function test_malformed_authorization_header_is_rejected(): void
    {
        $this->getJson(route('v1.me'), [
            'Authorization' => 'Basic xyz',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');

        $this->getJson(route('v1.me'), [
            'Authorization' => 'Bearer',
        ])
            ->assertUnauthorized();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withToken('cpat_this_token_does_not_exist_abcdefghij')
            ->getJson(route('v1.me'))
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_valid_token_authenticates_me_endpoint(): void
    {
        $user = User::factory()->create([
            'name' => 'API User',
            'email' => 'api@example.test',
            'status' => 'active',
        ]);

        $issued = $this->tokens->issue($user, 'CI');

        $this->withToken($issued['plain_text'])
            ->getJson(route('v1.me'))
            ->assertOk()
            ->assertJson([
                'id' => $user->id,
                'name' => 'API User',
                'email' => 'api@example.test',
            ])
            ->assertHeader('X-Api-Version', 'v1');
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $issued = $this->tokens->issue($user, 'Expired', expiresAt: now()->subMinute());

        $this->withToken($issued['plain_text'])
            ->getJson(route('v1.me'))
            ->assertUnauthorized();
    }

    public function test_revoked_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $issued = $this->tokens->issue($user, 'Revoked');
        $this->tokens->revoke($issued['token']);

        $this->withToken($issued['plain_text'])
            ->getJson(route('v1.me'))
            ->assertUnauthorized();
    }

    public function test_inactive_user_token_is_rejected(): void
    {
        $user = User::factory()->create(['status' => 'suspended']);
        $issued = $this->tokens->issue($user, 'Suspended');

        $this->withToken($issued['plain_text'])
            ->getJson(route('v1.me'))
            ->assertUnauthorized();
    }

    public function test_successful_auth_updates_last_used_at(): void
    {
        $user = User::factory()->create();
        $issued = $this->tokens->issue($user, 'Touch');

        $this->assertNull($issued['token']->fresh()->last_used_at);

        $this->withToken($issued['plain_text'])
            ->getJson(route('v1.me'))
            ->assertOk();

        $this->assertNotNull($issued['token']->fresh()->last_used_at);
    }

    public function test_issued_tokens_use_configured_prefix_and_are_hashed(): void
    {
        $user = User::factory()->create();
        $issued = $this->tokens->issue($user, 'Hash check');

        $this->assertTrue(str_starts_with($issued['plain_text'], 'cpat_'));
        $this->assertDatabaseMissing('api_tokens', [
            'id' => $issued['token']->id,
            'token_hash' => $issued['plain_text'],
        ]);
        $this->assertDatabaseHas('api_tokens', [
            'id' => $issued['token']->id,
            'token_hash' => $this->tokens->hash($issued['plain_text']),
            'token_prefix' => $this->tokens->prefixOf($issued['plain_text']),
        ]);
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Core\API\Services\ApiTokenService;
use Core\API\Support\ApiScope;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiPaginationAndFiltersTest extends TestCase
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
            'corepanel.rbac.cache.prefix' => 'test.rbac.api-pagination',
            'corepanel.api.rate_limit.enabled' => false,
            'corepanel.api.token_prefix' => 'cpat_',
            'corepanel.api.pagination.default_per_page' => 20,
            'corepanel.api.pagination.max_per_page' => 100,
        ]);
    }

    public function test_index_respects_per_page_and_page_query_params(): void
    {
        [$user, $client, $token] = $this->makeClientApiUser([ApiScope::SERVICE_READ]);

        Service::factory()->active()->forClient($client)->count(3)->create();

        $page1 = $this->withToken($token)
            ->getJson(route('v1.services.index', ['per_page' => 2, 'page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(2, 'data');

        $page2 = $this->withToken($token)
            ->getJson(route('v1.services.index', ['per_page' => 2, 'page' => 2]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(1, 'data');

        $this->assertNotEquals(
            $page1->json('data.0.id'),
            $page2->json('data.0.id'),
        );
    }

    public function test_per_page_above_max_is_rejected(): void
    {
        [$user, $client, $token] = $this->makeClientApiUser([ApiScope::SERVICE_READ]);

        $this->withToken($token)
            ->getJson(route('v1.services.index', ['per_page' => 101]))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_service_status_filter_and_sort_are_applied(): void
    {
        [$user, $client, $token] = $this->makeClientApiUser([ApiScope::SERVICE_READ]);

        $active = Service::factory()->active()->forClient($client)->create([
            'hostname' => 'alpha.example.test',
        ]);
        $suspended = Service::factory()->forClient($client)->create([
            'status' => ServiceStatus::Suspended,
            'hostname' => 'zeta.example.test',
        ]);

        $this->withToken($token)
            ->getJson(route('v1.services.index', [
                'status' => ServiceStatus::Active->value,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('meta.filters.status', ServiceStatus::Active->value);

        $this->withToken($token)
            ->getJson(route('v1.services.index', [
                'sort' => 'hostname',
                'dir' => 'desc',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $suspended->id)
            ->assertJsonPath('data.1.id', $active->id)
            ->assertJsonPath('meta.filters.sort', 'hostname')
            ->assertJsonPath('meta.filters.dir', 'desc');
    }

    public function test_client_search_filter_matches_company_name(): void
    {
        [$user, $client, $token] = $this->makeClientApiUser([ApiScope::CLIENT_READ], [
            'company_name' => 'Acme Hosting',
            'status' => ClientStatus::Active,
        ]);
        Client::factory()->create([
            'user_id' => $user->id,
            'company_name' => 'Other Corp',
        ])->users()->attach($user->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);

        $this->withToken($token)
            ->getJson(route('v1.clients.index', ['q' => 'Acme']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $client->id)
            ->assertJsonPath('meta.filters.q', 'Acme');
    }

    public function test_invalid_status_filter_returns_validation_error(): void
    {
        [$user, $client, $token] = $this->makeClientApiUser([ApiScope::INVOICE_READ]);

        $this->withToken($token)
            ->getJson(route('v1.invoices.index', ['status' => 'draft']))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->withToken($token)
            ->getJson(route('v1.invoices.index', ['status' => 'not-a-status']))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_foreign_client_id_filter_returns_not_found(): void
    {
        [$user, $client, $token] = $this->makeClientApiUser([ApiScope::SERVICE_READ]);
        $foreign = Client::factory()->create();

        $this->withToken($token)
            ->getJson(route('v1.services.index', ['client_id' => $foreign->id]))
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    /**
     * @param  list<string>  $scopes
     * @param  array<string, mixed>  $clientAttributes
     * @return array{0: User, 1: Client, 2: string}
     */
    private function makeClientApiUser(array $scopes, array $clientAttributes = []): array
    {
        $user = User::factory()->withRole('client')->create();
        $client = Client::factory()->create(array_merge([
            'user_id' => $user->id,
        ], $clientAttributes));
        $client->users()->attach($user->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);

        $token = $this->tokens->issue($user, 'API pagination', $scopes)['plain_text'];

        return [$user, $client, $token];
    }
}

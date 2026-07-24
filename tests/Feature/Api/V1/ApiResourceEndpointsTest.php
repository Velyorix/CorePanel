<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Core\API\Services\ApiTokenService;
use Core\API\Support\ApiScope;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Models\Invoice;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Nodes\Models\Node;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Tickets\Models\Ticket;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiResourceEndpointsTest extends TestCase
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
            'corepanel.rbac.cache.prefix' => 'test.rbac.api-resources',
            'corepanel.api.rate_limit.enabled' => false,
            'corepanel.api.token_prefix' => 'cpat_',
            'corepanel.tickets.notifications.enabled' => false,
            'corepanel.tickets.rate_limit.create.max_attempts' => 100,
            'corepanel.tickets.rate_limit.reply.max_attempts' => 100,
            'corepanel.tickets.numbering.prefix' => 'TK',
            'corepanel.tickets.numbering.padding' => 5,
            'corepanel.tickets.numbering.include_year' => true,
            'corepanel.tickets.numbering.reset_yearly' => true,
            'corepanel.tickets.numbering.separator' => '-',
        ]);
    }

    public function test_client_can_list_and_show_accessible_clients(): void
    {
        [$user, $client, $token] = $this->makeClientApiUser([ApiScope::CLIENT_READ]);
        $other = Client::factory()->create();

        $this->withToken($token)
            ->getJson(route('v1.clients.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $client->id)
            ->assertJsonMissing(['data' => [['id' => $other->id]]]);

        $this->withToken($token)
            ->getJson(route('v1.clients.show', $client))
            ->assertOk()
            ->assertJsonPath('data.company_name', $client->company_name);

        $this->withToken($token)
            ->getJson(route('v1.clients.show', $other))
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_service_endpoints_respect_ownership_and_scopes(): void
    {
        [$user, $client, $token] = $this->makeClientApiUser([
            ApiScope::SERVICE_READ,
            ApiScope::SERVICE_WRITE,
        ]);
        $service = Service::factory()->active()->forClient($client)->create();
        $foreign = Service::factory()->active()->create();

        $this->withToken($token)
            ->getJson(route('v1.services.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $service->id);

        $this->withToken($token)
            ->getJson(route('v1.services.show', $service))
            ->assertOk()
            ->assertJsonPath('data.status', ServiceStatus::Active->value);

        $this->withToken($token)
            ->getJson(route('v1.services.show', $foreign))
            ->assertNotFound();

        $readOnly = $this->tokens->issue($user, 'RO', [ApiScope::SERVICE_READ])['plain_text'];
        $this->withToken($readOnly)
            ->postJson(route('v1.services.restart', $service))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'insufficient_scope');
    }

    public function test_invoice_list_and_show_hide_drafts_and_foreign(): void
    {
        [$user, $client, $token] = $this->makeClientApiUser([ApiScope::INVOICE_READ]);
        $invoice = Invoice::factory()->unpaid()->create([
            'client_id' => $client->id,
            'total_amount' => '50.00',
            'subtotal' => '50.00',
        ]);
        $draft = Invoice::factory()->create([
            'client_id' => $client->id,
            'status' => InvoiceStatus::Draft,
            'total_amount' => '10.00',
            'subtotal' => '10.00',
        ]);
        $foreign = Invoice::factory()->unpaid()->create([
            'total_amount' => '20.00',
            'subtotal' => '20.00',
        ]);

        $response = $this->withToken($token)
            ->getJson(route('v1.invoices.index'))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($invoice->id, $ids);
        $this->assertNotContains($draft->id, $ids);
        $this->assertNotContains($foreign->id, $ids);

        $this->withToken($token)
            ->getJson(route('v1.invoices.show', $invoice))
            ->assertOk()
            ->assertJsonPath('data.invoice_number', $invoice->invoice_number);

        $this->withToken($token)
            ->getJson(route('v1.invoices.show', $draft))
            ->assertNotFound();
    }

    public function test_ticket_create_list_show_and_reply(): void
    {
        [$user, $client, $token] = $this->makeClientApiUser([
            ApiScope::TICKET_READ,
            ApiScope::TICKET_WRITE,
        ]);

        $create = $this->withToken($token)
            ->postJson(route('v1.tickets.store'), [
                'client_id' => $client->id,
                'subject' => 'API help',
                'message' => 'Need assistance via API.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.subject', 'API help');

        $ticketId = (int) $create->json('data.id');

        $this->withToken($token)
            ->getJson(route('v1.tickets.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $ticketId);

        $this->withToken($token)
            ->getJson(route('v1.tickets.show', $ticketId))
            ->assertOk()
            ->assertJsonPath('data.messages.0.message', 'Need assistance via API.');

        $this->withToken($token)
            ->postJson(route('v1.tickets.reply', $ticketId), [
                'message' => 'Follow-up from API.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.message', 'Follow-up from API.');
    }

    public function test_nodes_require_staff_permission(): void
    {
        [$clientUser, $client, $clientToken] = $this->makeClientApiUser([ApiScope::NODE_READ]);
        $node = Node::factory()->create();

        $this->withToken($clientToken)
            ->getJson(route('v1.nodes.index'))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');

        $admin = User::factory()->withRole('admin')->create();
        $adminToken = $this->tokens->issue($admin, 'Nodes', [ApiScope::NODE_READ])['plain_text'];

        $this->withToken($adminToken)
            ->getJson(route('v1.nodes.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $node->id)
            ->assertJsonMissingPath('data.0.credentials');

        $this->withToken($adminToken)
            ->getJson(route('v1.nodes.show', $node))
            ->assertOk()
            ->assertJsonPath('data.name', $node->name);
    }

    public function test_missing_scope_blocks_resource_index(): void
    {
        [$user, $client, $token] = $this->makeClientApiUser([ApiScope::ME]);

        $this->withToken($token)
            ->getJson(route('v1.services.index'))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'insufficient_scope');
    }

    /**
     * @param  list<string>  $scopes
     * @return array{0: User, 1: Client, 2: string}
     */
    private function makeClientApiUser(array $scopes): array
    {
        $user = User::factory()->withRole('client')->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $client->users()->attach($user->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);

        $token = $this->tokens->issue($user, 'API resources', $scopes)['plain_text'];

        return [$user, $client, $token];
    }
}

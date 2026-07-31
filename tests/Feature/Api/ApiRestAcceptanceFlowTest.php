<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Core\API\Models\ApiRequestLog;
use Core\API\Services\ApiTokenService;
use Core\API\Support\ApiScope;
use Core\API\Support\InternalApiSigner;
use Core\Billing\Events\InvoicePaid;
use Core\Billing\Models\Invoice;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Modules\Models\InstalledModule;
use Core\Nodes\Models\Node;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Webhooks\Enums\WebhookDeliveryStatus;
use Core\Webhooks\Enums\WebhookEvent;
use Core\Webhooks\Support\WebhookSigner;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end smoke covering public v1 + internal module API surfaces.
 */
class ApiRestAcceptanceFlowTest extends TestCase
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
            'corepanel.rbac.cache.prefix' => 'test.rbac.api-acceptance',
            'corepanel.api.rate_limit.enabled' => false,
            'corepanel.api.token_prefix' => 'cpat_',
            'corepanel.api.request_log.enabled' => true,
            'corepanel.api.webhooks.enabled' => true,
            'corepanel.api.webhooks.max_attempts' => 3,
            'corepanel.api.webhooks.backoff_seconds' => [1, 2, 3],
            'corepanel.api.internal.enabled' => true,
            'corepanel.api.internal.ip_whitelist.enabled' => false,
            'corepanel.api.internal.require_enabled_module' => true,
            'corepanel.tickets.notifications.enabled' => false,
            'corepanel.tickets.rate_limit.create.max_attempts' => 100,
            'corepanel.tickets.rate_limit.reply.max_attempts' => 100,
            'corepanel.tickets.numbering.prefix' => 'TK',
            'corepanel.tickets.numbering.padding' => 5,
            'corepanel.tickets.numbering.include_year' => true,
            'corepanel.tickets.numbering.reset_yearly' => true,
            'corepanel.tickets.numbering.separator' => '-',
            'corepanel.billing.manual_transfer.enabled' => true,
        ]);

        Cache::flush();
    }

    public function test_public_api_auth_scopes_envelope_resources_and_logging(): void
    {
        [$user, $client, $token] = $this->makeClientApiUser([
            ApiScope::ME,
            ApiScope::CLIENT_READ,
            ApiScope::SERVICE_READ,
            ApiScope::SERVICE_WRITE,
            ApiScope::INVOICE_READ,
            ApiScope::INVOICE_WRITE,
            ApiScope::TICKET_READ,
            ApiScope::TICKET_WRITE,
        ]);

        $service = Service::factory()->active()->forClient($client)->create([
            'hostname' => 'api-accept.example.test',
        ]);
        $invoice = Invoice::factory()->unpaid()->create([
            'client_id' => $client->id,
            'total_amount' => '25.00',
            'subtotal' => '25.00',
        ]);

        $me = $this->withToken($token)
            ->getJson(route('v1.me'), ['X-Request-Id' => 'accept-me-1']);

        $me->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('meta.request_id', 'accept-me-1')
            ->assertJsonMissingPath('error');

        $this->withToken($token)
            ->getJson(route('v1.clients.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $client->id)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);

        $this->withToken($token)
            ->getJson(route('v1.services.index', [
                'status' => ServiceStatus::Active->value,
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $service->id)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.filters.status', ServiceStatus::Active->value);

        $this->withToken($token)
            ->getJson(route('v1.invoices.show', $invoice))
            ->assertOk()
            ->assertJsonPath('data.invoice_number', $invoice->invoice_number);

        $this->withToken($token)
            ->postJson(route('v1.invoices.pay', $invoice))
            ->assertOk()
            ->assertJsonStructure(['data' => ['invoice', 'paid_with_credit_only'], 'meta']);

        $ticket = $this->withToken($token)
            ->postJson(route('v1.tickets.store'), [
                'client_id' => $client->id,
                'subject' => 'Acceptance ticket',
                'message' => 'Opened via API acceptance flow.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.subject', 'Acceptance ticket');

        $ticketId = (int) $ticket->json('data.id');

        $this->withToken($token)
            ->postJson(route('v1.tickets.reply', $ticketId), [
                'message' => 'Follow-up acceptance reply.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.message', 'Follow-up acceptance reply.');

        $readOnly = $this->tokens->issue($user, 'RO', [ApiScope::ME])['plain_text'];
        $this->withToken($readOnly)
            ->getJson(route('v1.services.index'))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'insufficient_scope')
            ->assertJsonMissingPath('data');

        $this->withoutToken()
            ->getJson(route('v1.me'))
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertJsonMissingPath('data')
            ->assertJsonMissingPath('meta');

        $this->assertDatabaseHas('api_request_logs', [
            'request_id' => 'accept-me-1',
            'user_id' => $user->id,
            'path' => '/api/v1/me',
            'status_code' => 200,
        ]);

        $this->assertGreaterThan(
            0,
            ApiRequestLog::query()->where('user_id', $user->id)->count(),
        );
    }

    public function test_rate_limit_returns_429_with_headers(): void
    {
        config([
            'corepanel.api.rate_limit.enabled' => true,
            'corepanel.api.rate_limit.max_attempts' => 2,
            'corepanel.api.rate_limit.decay_seconds' => 60,
        ]);
        Cache::flush();

        $this->getJson(route('v1.ping'))->assertOk()->assertHeader('X-RateLimit-Limit', '2');
        $this->getJson(route('v1.ping'))->assertOk()->assertHeader('X-RateLimit-Remaining', '0');

        $this->getJson(route('v1.ping'))
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limit_exceeded')
            ->assertJsonPath('error.details.retry_after', 60)
            ->assertHeader('Retry-After', '60')
            ->assertHeader('X-RateLimit-Remaining', '0');
    }

    public function test_webhook_subscription_dispatches_signed_delivery_on_invoice_paid(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $token = $this->tokens->issue($user, 'WH', [
            ApiScope::WEBHOOK_READ,
            ApiScope::WEBHOOK_WRITE,
        ])['plain_text'];

        $create = $this->withToken($token)
            ->postJson(route('v1.webhooks.store'), [
                'name' => 'Acceptance hooks',
                'url' => 'https://hooks.example.test/accept',
                'events' => [WebhookEvent::InvoicePaid->value],
            ])
            ->assertCreated();

        $secret = (string) $create->json('data.secret');
        $webhookId = (int) $create->json('data.id');
        $this->assertStringStartsWith('cwhsec_', $secret);

        Http::fake([
            'https://hooks.example.test/accept' => Http::response(['received' => true], 200),
        ]);

        $invoice = Invoice::factory()->unpaid()->create([
            'total_amount' => '9.99',
            'subtotal' => '9.99',
        ]);

        event(new InvoicePaid($invoice->fresh() ?? $invoice));

        Http::assertSent(function ($request) use ($secret, $invoice): bool {
            if ($request->url() !== 'https://hooks.example.test/accept') {
                return false;
            }

            $body = $request->body();
            $timestamp = $request->header('X-CorePanel-Timestamp')[0] ?? '';
            $signature = $request->header('X-CorePanel-Signature')[0] ?? '';
            $payload = json_decode($body, true);

            return ($payload['event'] ?? null) === WebhookEvent::InvoicePaid->value
                && ($payload['data']['invoice_id'] ?? null) === $invoice->id
                && WebhookSigner::verify($secret, $timestamp, $body, $signature);
        });

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_id' => $webhookId,
            'event' => WebhookEvent::InvoicePaid->value,
            'status' => WebhookDeliveryStatus::Delivered->value,
        ]);

        $this->withToken($token)
            ->getJson(route('v1.webhooks.deliveries', $webhookId))
            ->assertOk()
            ->assertJsonPath('data.0.status', WebhookDeliveryStatus::Delivered->value);
    }

    public function test_staff_nodes_and_internal_module_api_are_reachable(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $adminToken = $this->tokens->issue($admin, 'Nodes', [ApiScope::NODE_READ])['plain_text'];
        $node = Node::factory()->create(['name' => 'accept-node']);

        $this->withToken($adminToken)
            ->getJson(route('v1.nodes.index', ['q' => 'accept-node']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $node->id)
            ->assertJsonMissingPath('data.0.credentials');

        $clientUser = User::factory()->withRole('client')->create();
        $clientToken = $this->tokens->issue($clientUser, 'No nodes', [ApiScope::NODE_READ])['plain_text'];
        $this->withToken($clientToken)
            ->getJson(route('v1.nodes.index'))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');

        $moduleKey = 'accept-module';
        $moduleToken = 'modtok_accept_token';
        $hmacSecret = 'hmac_accept_secret';

        InstalledModule::query()->create([
            'name' => $moduleKey,
            'version' => '1.0.0',
            'enabled' => true,
            'config' => [
                'internal_api' => [
                    'token' => $moduleToken,
                    'hmac_secret' => $hmacSecret,
                ],
            ],
            'installed_at' => now(),
            'enabled_at' => now(),
            'updated_at' => now(),
        ]);

        $service = Service::factory()->active()->create();
        $payload = ['service_id' => $service->id];
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = InternalApiSigner::sign($hmacSecret, $timestamp, $nonce, $body);

        $this->call(
            'POST',
            route('internal.service.suspend'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_COREPANEL_MODULE' => $moduleKey,
                'HTTP_X_COREPANEL_MODULE_TOKEN' => $moduleToken,
                'HTTP_X_COREPANEL_TIMESTAMP' => $timestamp,
                'HTTP_X_COREPANEL_NONCE' => $nonce,
                'HTTP_X_COREPANEL_SIGNATURE' => $signature,
            ],
            $body,
        )
            ->assertOk()
            ->assertJsonPath('data.id', $service->id)
            ->assertJsonPath('data.status', ServiceStatus::Suspended->value);
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

        $token = $this->tokens->issue($user, 'API acceptance', $scopes)['plain_text'];

        return [$user, $client, $token];
    }
}

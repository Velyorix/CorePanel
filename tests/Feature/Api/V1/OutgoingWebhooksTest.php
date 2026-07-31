<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Core\API\Services\ApiTokenService;
use Core\API\Support\ApiScope;
use Core\Billing\Events\InvoicePaid;
use Core\Billing\Models\Invoice;
use Core\Webhooks\Enums\WebhookDeliveryStatus;
use Core\Webhooks\Enums\WebhookEvent;
use Core\Webhooks\Jobs\DeliverWebhookJob;
use Core\Webhooks\Models\Webhook;
use Core\Webhooks\Models\WebhookDelivery;
use Core\Webhooks\Support\WebhookSigner;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OutgoingWebhooksTest extends TestCase
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
            'corepanel.rbac.cache.prefix' => 'test.rbac.api-webhooks',
            'corepanel.api.rate_limit.enabled' => false,
            'corepanel.api.token_prefix' => 'cpat_',
            'corepanel.api.webhooks.enabled' => true,
            'corepanel.api.webhooks.timeout_seconds' => 5,
            'corepanel.api.webhooks.max_attempts' => 3,
            'corepanel.api.webhooks.backoff_seconds' => [1, 2, 3],
        ]);
    }

    public function test_can_subscribe_list_update_and_delete_webhook(): void
    {
        [$user, $token] = $this->makeApiUser([
            ApiScope::WEBHOOK_READ,
            ApiScope::WEBHOOK_WRITE,
        ]);

        $create = $this->withToken($token)
            ->postJson(route('v1.webhooks.store'), [
                'name' => 'Billing hooks',
                'url' => 'https://hooks.example.test/billing',
                'events' => [WebhookEvent::InvoicePaid->value],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Billing hooks')
            ->assertJsonPath('data.events.0', WebhookEvent::InvoicePaid->value);

        $secret = $create->json('data.secret');
        $this->assertIsString($secret);
        $this->assertStringStartsWith('cwhsec_', $secret);

        $id = (int) $create->json('data.id');

        $this->withToken($token)
            ->getJson(route('v1.webhooks.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonMissingPath('data.0.secret');

        $this->withToken($token)
            ->patchJson(route('v1.webhooks.update', $id), [
                'events' => ['*'],
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.events.0', '*')
            ->assertJsonPath('data.is_active', false);

        $this->withToken($token)
            ->deleteJson(route('v1.webhooks.destroy', $id))
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('webhooks', ['id' => $id]);
    }

    public function test_events_catalog_requires_read_scope(): void
    {
        [$user, $token] = $this->makeApiUser([ApiScope::ME]);

        $this->withToken($token)
            ->getJson(route('v1.webhooks.events'))
            ->assertForbidden();

        $readToken = $this->tokens->issue($user, 'WH read', [ApiScope::WEBHOOK_READ])['plain_text'];

        $this->withToken($readToken)
            ->getJson(route('v1.webhooks.events'))
            ->assertOk()
            ->assertJsonPath('data.events.0', WebhookEvent::ServiceCreated->value);
    }

    public function test_invoice_paid_dispatches_signed_webhook_delivery(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $webhook = Webhook::factory()->for($user)->listening([WebhookEvent::InvoicePaid->value])->create([
            'url' => 'https://hooks.example.test/paid',
            'secret' => 'cwhsec_test_secret_value_123456789012',
        ]);
        $invoice = Invoice::factory()->unpaid()->create([
            'total_amount' => '42.00',
            'subtotal' => '42.00',
        ]);

        Http::fake([
            'https://hooks.example.test/paid' => Http::response(['ok' => true], 200),
        ]);

        event(new InvoicePaid($invoice->fresh() ?? $invoice));

        Http::assertSent(function ($request) use ($webhook, $invoice): bool {
            if ($request->url() !== 'https://hooks.example.test/paid') {
                return false;
            }

            $body = $request->body();
            $timestamp = $request->header('X-CorePanel-Timestamp')[0] ?? '';
            $signature = $request->header('X-CorePanel-Signature')[0] ?? '';
            $event = $request->header('X-CorePanel-Event')[0] ?? '';

            $payload = json_decode($body, true);

            return $event === WebhookEvent::InvoicePaid->value
                && ($payload['data']['invoice_id'] ?? null) === $invoice->id
                && WebhookSigner::verify(
                    (string) $webhook->fresh()->secret,
                    $timestamp,
                    $body,
                    $signature,
                );
        });

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_id' => $webhook->id,
            'event' => WebhookEvent::InvoicePaid->value,
            'status' => WebhookDeliveryStatus::Delivered->value,
        ]);
    }

    public function test_failed_delivery_is_retried_then_marked_failed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $webhook = Webhook::factory()->for($user)->listening(['*'])->create([
            'url' => 'https://hooks.example.test/fail',
        ]);

        $delivery = WebhookDelivery::factory()->for($webhook)->create([
            'event' => WebhookEvent::TicketCreated->value,
            'status' => WebhookDeliveryStatus::Pending,
            'attempt' => 0,
            'payload' => [
                'event' => WebhookEvent::TicketCreated->value,
                'timestamp' => now()->toIso8601String(),
                'data' => ['ticket_id' => 1],
            ],
        ]);

        Http::fake([
            'https://hooks.example.test/fail' => Http::response('nope', 500),
        ]);

        (new DeliverWebhookJob($delivery->id))->handle(app(\Core\Webhooks\Services\WebhookDeliveryService::class));

        $delivery->refresh();
        $this->assertSame(WebhookDeliveryStatus::Retrying, $delivery->status);
        $this->assertSame(1, $delivery->attempt);
        Queue::assertPushed(DeliverWebhookJob::class);

        $delivery->forceFill(['attempt' => 3, 'status' => WebhookDeliveryStatus::Retrying])->save();
        (new DeliverWebhookJob($delivery->id))->handle(app(\Core\Webhooks\Services\WebhookDeliveryService::class));

        $delivery->refresh();
        $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->status);
        $this->assertSame(4, $delivery->attempt);
    }

    public function test_foreign_webhook_is_hidden(): void
    {
        [$user, $token] = $this->makeApiUser([ApiScope::WEBHOOK_READ]);
        $other = Webhook::factory()->create();

        $this->withToken($token)
            ->getJson(route('v1.webhooks.show', $other))
            ->assertNotFound();
    }

    public function test_http_url_is_rejected(): void
    {
        [$user, $token] = $this->makeApiUser([ApiScope::WEBHOOK_WRITE]);

        $this->withToken($token)
            ->postJson(route('v1.webhooks.store'), [
                'name' => 'Insecure',
                'url' => 'http://hooks.example.test/billing',
                'events' => [WebhookEvent::InvoicePaid->value],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    /**
     * @param  list<string>  $scopes
     * @return array{0: User, 1: string}
     */
    private function makeApiUser(array $scopes): array
    {
        $user = User::factory()->withRole('admin')->create();
        $token = $this->tokens->issue($user, 'Webhooks', $scopes)['plain_text'];

        return [$user, $token];
    }
}

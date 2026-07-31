<?php

namespace Tests\Feature\Api\Internal;

use Core\API\Support\InternalApiSigner;
use Core\Modules\Models\InstalledModule;
use Core\Nodes\Models\Node;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Core\Orders\Models\OrderItem;
use Core\Products\Models\Product;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Sync\Services\NodeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalApiTest extends TestCase
{
    use RefreshDatabase;

    private const MODULE = 'demo-provider';

    private const TOKEN = 'modtok_test_internal_token_123';

    private const HMAC_SECRET = 'hmac_test_internal_secret_456';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'corepanel.api.internal.enabled' => true,
            'corepanel.api.internal.token' => '',
            'corepanel.api.internal.hmac_secret' => '',
            'corepanel.api.internal.require_enabled_module' => true,
            'corepanel.api.internal.ip_whitelist.enabled' => false,
            'corepanel.api.internal.max_skew_seconds' => 300,
            'corepanel.api.request_log.enabled' => false,
            'corepanel.api.webhooks.enabled' => false,
            'corepanel.nodes.sync.enabled' => true,
        ]);

        InstalledModule::query()->create([
            'name' => self::MODULE,
            'version' => '1.0.0',
            'enabled' => true,
            'config' => [
                'internal_api' => [
                    'token' => self::TOKEN,
                    'hmac_secret' => self::HMAC_SECRET,
                ],
            ],
            'installed_at' => now(),
            'enabled_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_service_create_from_paid_order(): void
    {
        $product = Product::factory()->create();
        $order = Order::factory()->paid()->create(['status' => OrderStatus::Paid]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        $response = $this->signedPost(route('internal.service.create'), [
            'order_id' => $order->id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.created.0.client_id', $order->client_id)
            ->assertJsonPath('data.created.0.product_id', $product->id);

        $this->assertDatabaseHas('services', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'status' => ServiceStatus::Pending->value,
        ]);
    }

    public function test_service_suspend_and_terminate(): void
    {
        $service = Service::factory()->active()->create();

        $this->signedPost(route('internal.service.suspend'), [
            'service_id' => $service->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $service->id)
            ->assertJsonPath('data.status', ServiceStatus::Suspended->value);

        $this->signedPost(route('internal.service.terminate'), [
            'service_id' => $service->id,
        ], nonce: 'nonce-terminate-1')
            ->assertOk()
            ->assertJsonPath('data.status', ServiceStatus::Terminated->value);
    }

    public function test_node_sync_returns_outcome(): void
    {
        $node = Node::factory()->create();

        $sync = \Mockery::mock(NodeSyncService::class);
        $sync->shouldReceive('syncNode')
            ->once()
            ->withArgs(fn (Node $arg): bool => $arg->id === $node->id)
            ->andReturn('synced');
        $this->app->instance(NodeSyncService::class, $sync);

        $this->signedPost(route('internal.node.sync'), [
            'node_id' => $node->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.outcome', 'synced')
            ->assertJsonPath('data.node.id', $node->id);
    }

    public function test_missing_signature_is_rejected(): void
    {
        $this->postJson(route('internal.service.suspend'), [
            'service_id' => 1,
        ], [
            'X-CorePanel-Module' => self::MODULE,
            'X-CorePanel-Module-Token' => self::TOKEN,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->signedPost(route('internal.service.suspend'), [
            'service_id' => 1,
        ], token: 'wrong-token')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_replayed_nonce_is_rejected(): void
    {
        $service = Service::factory()->active()->create();
        $payload = ['service_id' => $service->id];
        $nonce = 'fixed-nonce-replay-test';

        $this->signedPost(route('internal.service.suspend'), $payload, nonce: $nonce)
            ->assertOk();

        $this->signedPost(route('internal.service.suspend'), $payload, nonce: $nonce)
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_ip_whitelist_blocks_disallowed_clients(): void
    {
        config([
            'corepanel.api.internal.ip_whitelist.enabled' => true,
            'corepanel.api.internal.ip_whitelist.allowed' => ['203.0.113.10'],
        ]);

        $service = Service::factory()->active()->create();

        $this->signedPost(route('internal.service.suspend'), [
            'service_id' => $service->id,
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_disabled_module_is_forbidden(): void
    {
        InstalledModule::query()->where('name', self::MODULE)->update(['enabled' => false]);

        $service = Service::factory()->active()->create();

        $this->signedPost(route('internal.service.suspend'), [
            'service_id' => $service->id,
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signedPost(
        string $uri,
        array $payload,
        ?string $nonce = null,
        ?string $token = null,
    ) {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $nonce ??= (string) Str::uuid();
        $signature = InternalApiSigner::sign(self::HMAC_SECRET, $timestamp, $nonce, $body);

        return $this->call(
            'POST',
            $uri,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_COREPANEL_MODULE' => self::MODULE,
                'HTTP_X_COREPANEL_MODULE_TOKEN' => $token ?? self::TOKEN,
                'HTTP_X_COREPANEL_TIMESTAMP' => $timestamp,
                'HTTP_X_COREPANEL_NONCE' => $nonce,
                'HTTP_X_COREPANEL_SIGNATURE' => $signature,
            ],
            $body,
        );
    }
}

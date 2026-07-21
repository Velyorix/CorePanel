<?php

namespace Tests\Feature\Servers;

use App\Models\User;
use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeConnectionTestService;
use Core\Providers\Contracts\NodeProviderInterface;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\NodeResourcesResponse;
use Core\Providers\Services\ProviderRegistry;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Stubs\StubServerProvider;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNodeConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-node-connection',
            'corepanel.provisioning.stub.enabled' => true,
        ]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));
        $registry->registerNode(app(StubNodeProvider::class));
    }

    public function test_connection_test_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NodeConnectionTestService::class),
            app(NodeConnectionTestService::class),
        );
    }

    public function test_admin_can_test_connection_from_create_form(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->post(route('admin.nodes.test-connection'), [
                'name' => 'Draft Node',
                'hostname' => 'draft.example.test',
                'module' => 'stub',
                'credentials' => ['api_key' => 'test-key'],
            ])
            ->assertRedirect()
            ->assertSessionHas('connection_status');

        $this->assertStringContainsString(
            'draft.example.test',
            (string) session('connection_status'),
        );
    }

    public function test_create_form_connection_test_requires_module(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->post(route('admin.nodes.test-connection'), [
                'name' => 'Draft Node',
                'hostname' => 'draft.example.test',
            ])
            ->assertSessionHasErrors('module');
    }

    public function test_admin_can_test_connection_for_existing_node(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $node = Node::factory()->forModule('stub')->create([
            'hostname' => 'live.example.test',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.nodes.index'))
            ->post(route('admin.nodes.test-connection.node', $node))
            ->assertRedirect(route('admin.nodes.index'))
            ->assertSessionHas('connection_status');
    }

    public function test_connection_test_fails_when_node_provider_missing(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        app(ProviderRegistry::class)->registerServer(new class implements \Core\Providers\Contracts\ServerProviderInterface
        {
            public function key(): string
            {
                return 'server-only';
            }

            public function label(): string
            {
                return 'Server Only';
            }

            public function create(\Core\Providers\DataTransferObjects\ProvisioningRequest $request): \Core\Providers\DataTransferObjects\ProvisioningResponse
            {
                return \Core\Providers\DataTransferObjects\ProvisioningResponse::success();
            }

            public function suspend(\Core\Providers\DataTransferObjects\ProvisioningRequest $request): \Core\Providers\DataTransferObjects\ProvisioningResponse
            {
                return \Core\Providers\DataTransferObjects\ProvisioningResponse::success();
            }

            public function unsuspend(\Core\Providers\DataTransferObjects\ProvisioningRequest $request): \Core\Providers\DataTransferObjects\ProvisioningResponse
            {
                return \Core\Providers\DataTransferObjects\ProvisioningResponse::success();
            }

            public function terminate(\Core\Providers\DataTransferObjects\ProvisioningRequest $request): \Core\Providers\DataTransferObjects\ProvisioningResponse
            {
                return \Core\Providers\DataTransferObjects\ProvisioningResponse::success();
            }

            public function reinstall(\Core\Providers\DataTransferObjects\ProvisioningRequest $request): \Core\Providers\DataTransferObjects\ProvisioningResponse
            {
                return \Core\Providers\DataTransferObjects\ProvisioningResponse::success();
            }
        });

        $this->actingAs($admin)
            ->post(route('admin.nodes.test-connection'), [
                'hostname' => 'orphan.example.test',
                'module' => 'server-only',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('module');
    }

    public function test_support_cannot_test_connection(): void
    {
        $support = User::factory()->withRole('support')->create();
        $node = Node::factory()->forModule('stub')->create();

        $this->actingAs($support)
            ->post(route('admin.nodes.test-connection'), [
                'hostname' => 'blocked.example.test',
                'module' => 'stub',
            ])
            ->assertForbidden();

        $this->actingAs($support)
            ->post(route('admin.nodes.test-connection.node', $node))
            ->assertForbidden();
    }

    public function test_failing_provider_returns_error_flash(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        app(ProviderRegistry::class)->registerNode(new class implements NodeProviderInterface
        {
            public function key(): string
            {
                return 'failing';
            }

            public function label(): string
            {
                return 'Failing';
            }

            public function testConnection(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::failed('Remote API rejected credentials.');
            }

            public function sync(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::success();
            }

            public function getResources(NodeConnectionRequest $node): NodeResourcesResponse
            {
                return NodeResourcesResponse::success(
                    new \Core\Providers\DataTransferObjects\NodeResourcesData(
                        maxServices: 1,
                        currentServices: 0,
                        capacityAvailable: true,
                    ),
                );
            }
        });

        $this->actingAs($admin)
            ->post(route('admin.nodes.test-connection'), [
                'hostname' => 'fail.example.test',
                'module' => 'failing',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('connection');
    }
}

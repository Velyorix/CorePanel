<?php

namespace Tests\Feature\Servers;

use App\Models\User;
use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Enums\NodeType;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Providers\Services\ProviderRegistry;
use Core\Providers\Stubs\StubServerProvider;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminNodeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-nodes',
            'corepanel.provisioning.stub.enabled' => true,
        ]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));
    }

    public function test_admin_can_view_nodes_index_and_create_form(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $node = Node::factory()->forModule('stub')->create([
            'name' => 'EU Game Node',
            'hostname' => 'eu-game.example.test',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.nodes.index'))
            ->assertOk()
            ->assertSee('EU Game Node')
            ->assertSee($node->hostname);

        $this->actingAs($admin)
            ->get(route('admin.nodes.create'))
            ->assertOk()
            ->assertSee(__('Create server'))
            ->assertSee(__('Provider module'))
            ->assertSee(__('Credentials'))
            ->assertSee(__('Capacity limits'))
            ->assertSee('stub');
    }

    public function test_admin_can_create_server_with_module_binding(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $group = NodeGroup::factory()->create(['key' => 'eu-west']);

        $this->actingAs($admin)
            ->post(route('admin.nodes.store'), [
                'name' => 'Ptero EU-1',
                'hostname' => 'ptero-eu-1.example.test',
                'type' => NodeType::Game->value,
                'module' => 'stub',
                'ip_address' => '203.0.113.44',
                'api_url' => 'https://panel.example.test',
                'status' => NodeStatus::Active->value,
                'max_services' => 40,
                'max_cpu_cores' => 12,
                'max_ram_mb' => 49152,
                'max_disk_gb' => 1000,
                'sort_order' => 5,
                'node_group_id' => $group->id,
            ])
            ->assertRedirect(route('admin.nodes.index'))
            ->assertSessionHas('status');

        $node = Node::query()->where('hostname', 'ptero-eu-1.example.test')->firstOrFail();
        $this->assertSame('Ptero EU-1', $node->name);
        $this->assertSame(NodeType::Game, $node->type);
        $this->assertSame('stub', $node->module);
        $this->assertSame('203.0.113.44', $node->ip_address);
        $this->assertSame('https://panel.example.test', $node->api_url);
        $this->assertSame($group->id, $node->node_group_id);
        $this->assertSame(40, $node->max_services);
        $this->assertSame(12, $node->max_cpu_cores);
        $this->assertSame(49152, $node->max_ram_mb);
        $this->assertSame(1000, $node->max_disk_gb);
    }

    public function test_admin_can_create_server_with_encrypted_credentials(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->post(route('admin.nodes.store'), [
                'name' => 'Secure Node',
                'hostname' => 'secure.example.test',
                'type' => NodeType::Vps->value,
                'module' => 'stub',
                'credentials' => [
                    'api_key' => 'panel-secret-key',
                    'ssh_password' => 'ssh-secret-pass',
                ],
            ])
            ->assertRedirect(route('admin.nodes.index'));

        $node = Node::query()->where('hostname', 'secure.example.test')->firstOrFail();
        $raw = DB::table('nodes')->where('id', $node->id)->value('credentials');

        $this->assertStringNotContainsString('panel-secret-key', (string) $raw);
        $this->assertStringNotContainsString('ssh-secret-pass', (string) $raw);
        $this->assertSame('panel-secret-key', $node->credentials['api_key']);
        $this->assertTrue($node->hasConfiguredCredentials());
    }

    public function test_support_cannot_create_servers(): void
    {
        $support = User::factory()->withRole('support')->create();

        $this->actingAs($support)
            ->get(route('admin.nodes.index'))
            ->assertForbidden();

        $this->actingAs($support)
            ->get(route('admin.nodes.create'))
            ->assertForbidden();

        $this->actingAs($support)
            ->post(route('admin.nodes.store'), [
                'name' => 'Blocked',
                'hostname' => 'blocked.example.test',
                'type' => NodeType::Vps->value,
            ])
            ->assertForbidden();
    }

    public function test_create_rejects_unknown_module(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->post(route('admin.nodes.store'), [
                'name' => 'Bad Module',
                'hostname' => 'bad.example.test',
                'type' => NodeType::Vps->value,
                'module' => 'unknown-module',
            ])
            ->assertSessionHasErrors('module');
    }

    public function test_guest_is_redirected_from_nodes_admin(): void
    {
        $this->get(route('admin.nodes.index'))
            ->assertRedirect(route('login'));
    }
}

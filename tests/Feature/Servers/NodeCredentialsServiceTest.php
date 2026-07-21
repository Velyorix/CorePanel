<?php

namespace Tests\Feature\Servers;

use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeCredentialsService;
use Core\Nodes\Services\NodeService;
use Core\Nodes\DataTransferObjects\NodeData;
use Core\Nodes\Enums\NodeType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NodeCredentialsServiceTest extends TestCase
{
    use RefreshDatabase;

    private NodeCredentialsService $credentials;

    protected function setUp(): void
    {
        parent::setUp();

        $this->credentials = app(NodeCredentialsService::class);
    }

    public function test_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NodeCredentialsService::class),
            app(NodeCredentialsService::class),
        );
    }

    public function test_build_for_create_filters_empty_fields(): void
    {
        $built = $this->credentials->buildForCreate([
            'api_key' => 'secret-key',
            'api_token' => '',
            'ssh_username' => 'root',
        ]);

        $this->assertSame([
            'api_key' => 'secret-key',
            'ssh_username' => 'root',
        ], $built);
    }

    public function test_credentials_are_encrypted_at_rest_on_node(): void
    {
        $node = Node::factory()->create([
            'credentials' => [
                'api_key' => 'super-secret-api-key',
                'ssh_password' => 'ssh-pass-123',
            ],
        ]);

        $raw = DB::table('nodes')->where('id', $node->id)->value('credentials');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('super-secret-api-key', $raw);
        $this->assertStringNotContainsString('ssh-pass-123', $raw);

        $node->refresh();
        $this->assertSame('super-secret-api-key', $node->credentials['api_key']);
    }

    public function test_merge_for_update_preserves_existing_secrets_when_blank(): void
    {
        $node = Node::factory()->create([
            'credentials' => [
                'api_key' => 'keep-me',
                'ssh_password' => 'old-pass',
            ],
        ]);

        $merged = $this->credentials->mergeForUpdate($node, [
            'api_key' => '',
            'ssh_username' => 'deploy',
        ]);

        $this->assertSame([
            'api_key' => 'keep-me',
            'ssh_username' => 'deploy',
            'ssh_password' => 'old-pass',
        ], $merged);
    }

    public function test_node_service_update_applies_credential_merge(): void
    {
        $node = app(NodeService::class)->create(NodeData::fromArray([
            'name' => 'Cred Node',
            'hostname' => 'cred.example.test',
            'type' => NodeType::Vps->value,
            'credentials' => ['api_key' => 'initial-key'],
        ]));

        app(NodeService::class)->update($node, NodeData::fromArray([
            'name' => 'Cred Node',
            'hostname' => 'cred.example.test',
            'type' => NodeType::Vps->value,
            'credentials' => [
                'api_key' => '',
                'api_token' => 'token-2',
            ],
        ]));

        $node->refresh();
        $this->assertSame('initial-key', $node->credentials['api_key']);
        $this->assertSame('token-2', $node->credentials['api_token']);
    }

    public function test_configured_keys_and_masked_labels(): void
    {
        $node = Node::factory()->create([
            'credentials' => ['api_key' => 'x', 'ssh_username' => 'root'],
        ]);

        $this->assertTrue($this->credentials->hasConfiguredCredentials($node));
        $this->assertSame(['api_key', 'ssh_username'], $this->credentials->configuredKeys($node));
        $this->assertArrayHasKey('api_key', $this->credentials->maskedLabels($node));
    }
}

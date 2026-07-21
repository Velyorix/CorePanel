<?php

namespace Tests\Feature\Nodes;

use Core\Nodes\Exceptions\NodeSecurityException;
use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeAuditLogger;
use Core\Nodes\Services\NodeConnectionSecurityService;
use Core\Nodes\Services\NodeConnectionTestService;
use Core\Nodes\Services\NodeHttpClient;
use Core\Nodes\Services\NodeIpWhitelistService;
use Core\Providers\Contracts\NodeProviderInterface;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\Enums\ProviderOperationStatus;
use Core\Providers\Services\ProviderRegistry;
use Core\Support\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeInfrastructureSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.nodes.security.tls.required' => true,
            'corepanel.nodes.security.tls.verify_ssl' => true,
            'corepanel.nodes.security.ip_whitelist.enabled' => false,
            'corepanel.nodes.security.audit.enabled' => true,
        ]);
    }

    public function test_http_client_rejects_insecure_api_urls_when_tls_is_required(): void
    {
        $this->expectException(NodeSecurityException::class);
        $this->expectExceptionMessage('must use HTTPS');

        app(NodeHttpClient::class)->assertSecureApiUrl('http://panel.example.test');
    }

    public function test_http_client_allows_https_api_urls(): void
    {
        app(NodeHttpClient::class)->assertSecureApiUrl('https://panel.example.test/api');

        $this->assertTrue(true);
    }

    public function test_ip_whitelist_blocks_targets_outside_allowed_ranges(): void
    {
        config([
            'corepanel.nodes.security.ip_whitelist.enabled' => true,
            'corepanel.nodes.security.ip_whitelist.allow_private' => false,
            'corepanel.nodes.security.ip_whitelist.allowed' => ['203.0.113.0/24'],
        ]);

        $request = NodeConnectionRequest::fromArray([
            'hostname' => 'node.example.test',
            'ip_address' => '198.51.100.10',
            'module' => 'stub',
        ]);

        $this->expectException(NodeSecurityException::class);
        $this->expectExceptionMessage('not allowed');

        app(NodeIpWhitelistService::class)->assertAllowed($request);
    }

    public function test_ip_whitelist_allows_matching_ip_addresses(): void
    {
        config([
            'corepanel.nodes.security.ip_whitelist.enabled' => true,
            'corepanel.nodes.security.ip_whitelist.allow_private' => false,
            'corepanel.nodes.security.ip_whitelist.allowed' => ['198.51.100.10'],
        ]);

        $request = NodeConnectionRequest::fromArray([
            'hostname' => 'node.example.test',
            'ip_address' => '198.51.100.10',
            'module' => 'stub',
        ]);

        app(NodeIpWhitelistService::class)->assertAllowed($request);

        $this->assertTrue(true);
    }

    public function test_connection_test_service_blocks_http_api_before_provider_call(): void
    {
        config(['corepanel.nodes.security.tls.required' => true]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerNode(new class implements NodeProviderInterface
        {
            public function key(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'Stub';
            }

            public function testConnection(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::success('should-not-run');
            }

            public function sync(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::success();
            }

            public function getResources(NodeConnectionRequest $node): \Core\Providers\DataTransferObjects\NodeResourcesResponse
            {
                return \Core\Providers\DataTransferObjects\NodeResourcesResponse::failed('not-used');
            }
        });

        $response = app(NodeConnectionTestService::class)->testPayload([
            'hostname' => 'node.example.test',
            'module' => 'stub',
            'api_url' => 'http://panel.example.test',
        ]);

        $this->assertSame(ProviderOperationStatus::Failed, $response->status);
        $this->assertStringContainsString('HTTPS', (string) $response->message);
    }

    public function test_node_audit_logger_records_access_events(): void
    {
        $node = Node::factory()->create([
            'hostname' => 'audit-node.example.test',
        ]);

        app(NodeAuditLogger::class)->log(
            action: NodeAuditLogger::ACTION_VIEWED,
            node: $node,
            after: ['hostname' => $node->hostname],
            actorId: 1,
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => NodeAuditLogger::ACTION_VIEWED,
            'entity_type' => Node::class,
            'entity_id' => $node->id,
            'actor_id' => 1,
        ]);

        $this->assertInstanceOf(AuditLog::class, AuditLog::query()->latest('id')->first());
    }

    public function test_connection_security_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NodeConnectionSecurityService::class),
            app(NodeConnectionSecurityService::class),
        );
    }
}

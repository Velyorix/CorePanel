<?php

namespace Tests\Unit\Nodes;

use Core\Nodes\DataTransferObjects\NodeSshConnection;
use Core\Nodes\Services\NodeSshClient;
use Core\Nodes\Services\NodeSshProbeService;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\Enums\ProviderOperationStatus;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class NodeSshProbeServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_supports_node_when_ssh_credentials_are_complete(): void
    {
        $service = new NodeSshProbeService(Mockery::mock(NodeSshClient::class));

        $this->assertTrue($service->supports($this->nodeRequest([
            'credentials' => [
                'ssh_username' => 'root',
                'ssh_private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nabc\n-----END OPENSSH PRIVATE KEY-----",
            ],
        ])));
    }

    public function test_does_not_support_node_without_ssh_credentials(): void
    {
        $service = new NodeSshProbeService(Mockery::mock(NodeSshClient::class));

        $this->assertFalse($service->supports($this->nodeRequest()));
    }

    public function test_test_connection_uses_ssh_client_ping(): void
    {
        $client = Mockery::mock(NodeSshClient::class);
        $client->shouldReceive('ping')
            ->once()
            ->with(Mockery::type(NodeSshConnection::class));

        $service = new NodeSshProbeService($client);

        $response = $service->testConnection($this->nodeRequest([
            'credentials' => [
                'ssh_username' => 'deploy',
                'ssh_password' => 'secret',
            ],
            'ip_address' => '10.0.0.5',
        ]));

        $this->assertSame(ProviderOperationStatus::Success, $response->status);
        $this->assertSame('ssh', $response->payload['transport'] ?? null);
        $this->assertSame('10.0.0.5', $response->payload['host'] ?? null);
    }

    public function test_test_connection_returns_failure_when_ssh_ping_fails(): void
    {
        $client = Mockery::mock(NodeSshClient::class);
        $client->shouldReceive('ping')
            ->once()
            ->andThrow(new RuntimeException('SSH authentication failed.'));

        $service = new NodeSshProbeService($client);

        $response = $service->testConnection($this->nodeRequest([
            'credentials' => [
                'ssh_username' => 'deploy',
                'ssh_password' => 'secret',
            ],
        ]));

        $this->assertSame(ProviderOperationStatus::Failed, $response->status);
        $this->assertSame('SSH authentication failed.', $response->message);
    }

    public function test_collect_resources_parses_ssh_probe_output(): void
    {
        $client = Mockery::mock(NodeSshClient::class);
        $client->shouldReceive('run')
            ->once()
            ->andReturn(implode("\n", [
                'LOAD=1.42',
                'RAM_MB=4096',
                'DISK_GB=250',
                'CPU=37',
                'NET_IN=12',
                'NET_OUT=8',
                'UPTIME=86400',
            ]));

        $service = new NodeSshProbeService($client);

        $response = $service->collectResources($this->nodeRequest([
            'credentials' => [
                'ssh_username' => 'root',
                'ssh_password' => 'secret',
            ],
            'max_services' => 50,
            'max_ram_mb' => 8192,
            'max_disk_gb' => 500,
        ]));

        $this->assertSame(ProviderOperationStatus::Success, $response->status);
        $this->assertSame(37.0, $response->resources->cpuUsage);
        $this->assertSame(4096.0, $response->resources->ramUsage);
        $this->assertSame(250.0, $response->resources->diskUsage);
        $this->assertSame(12.0, $response->resources->networkIn);
        $this->assertSame(8.0, $response->resources->networkOut);
        $this->assertSame(1.42, $response->resources->loadAverage);
        $this->assertTrue($response->resources->capacityAvailable);
        $this->assertSame(86400, $response->payload['uptime_seconds'] ?? null);
    }

    public function test_collect_resources_fails_when_probe_output_is_empty(): void
    {
        $client = Mockery::mock(NodeSshClient::class);
        $client->shouldReceive('run')
            ->once()
            ->andReturn('');

        $service = new NodeSshProbeService($client);

        $response = $service->collectResources($this->nodeRequest([
            'credentials' => [
                'ssh_username' => 'root',
                'ssh_password' => 'secret',
            ],
        ]));

        $this->assertSame(ProviderOperationStatus::Failed, $response->status);
        $this->assertStringContainsString('empty data', strtolower((string) $response->message));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function nodeRequest(array $overrides = []): NodeConnectionRequest
    {
        return NodeConnectionRequest::fromArray(array_merge([
            'id' => 1,
            'hostname' => 'node.example.test',
            'module' => 'stub',
        ], $overrides));
    }
}

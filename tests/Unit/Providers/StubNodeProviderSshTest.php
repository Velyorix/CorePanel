<?php

namespace Tests\Unit\Providers;

use Core\Nodes\Services\NodeSshProbeService;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\NodeResourcesData;
use Core\Providers\DataTransferObjects\NodeResourcesResponse;
use Core\Providers\Enums\ProviderOperationStatus;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Stubs\StubServerProvider;
use Mockery;
use Tests\TestCase;

class StubNodeProviderSshTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_stub_node_provider_delegates_to_ssh_probe_when_credentials_exist(): void
    {
        $sshProbe = Mockery::mock(NodeSshProbeService::class);
        $sshProbe->shouldReceive('supports')->once()->andReturn(true);
        $sshProbe->shouldReceive('testConnection')
            ->once()
            ->andReturn(NodeOperationResponse::success(
                message: 'SSH connection successful.',
                payload: ['transport' => 'ssh'],
            ));
        $sshProbe->shouldReceive('supports')->once()->andReturn(true);
        $sshProbe->shouldReceive('collectResources')
            ->once()
            ->andReturn(NodeResourcesResponse::success(
                new NodeResourcesData(cpuUsage: 12.5, ramUsage: 2048.0, capacityAvailable: true),
                payload: ['transport' => 'ssh'],
            ));

        $provider = new StubNodeProvider(app(StubServerProvider::class), $sshProbe);
        $node = NodeConnectionRequest::fromArray([
            'id' => 3,
            'hostname' => 'node-01.example.test',
            'module' => 'stub',
            'credentials' => [
                'ssh_username' => 'root',
                'ssh_password' => 'secret',
            ],
        ]);

        $connection = $provider->testConnection($node);
        $resources = $provider->getResources($node);

        $this->assertSame(ProviderOperationStatus::Success, $connection->status);
        $this->assertSame('ssh', $connection->payload['transport'] ?? null);
        $this->assertSame(12.5, $resources->resources->cpuUsage);
        $this->assertSame('ssh', $resources->payload['transport'] ?? null);
    }
}

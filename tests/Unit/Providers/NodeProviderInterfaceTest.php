<?php

namespace Tests\Unit\Providers;

use Core\Providers\Contracts\NodeProviderInterface;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\NodeResourcesData;
use Core\Providers\DataTransferObjects\NodeResourcesResponse;
use Core\Providers\Enums\ProviderOperationStatus;
use PHPUnit\Framework\TestCase;

class NodeProviderInterfaceTest extends TestCase
{
    public function test_contract_can_be_implemented(): void
    {
        $node = NodeConnectionRequest::fromArray([
            'id' => 1,
            'module' => 'pterodactyl',
            'name' => 'Node 01',
            'hostname' => 'node-01.example.test',
            'api_url' => 'https://panel.example.test',
            'credentials' => ['api_key' => 'secret'],
        ]);

        $provider = new class implements NodeProviderInterface
        {
            public function key(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'Stub Node Provider';
            }

            public function testConnection(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::success(
                    message: 'Connected to '.$node->hostname,
                );
            }

            public function sync(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::success(payload: ['node_id' => $node->id]);
            }

            public function getResources(NodeConnectionRequest $node): NodeResourcesResponse
            {
                return NodeResourcesResponse::success(new NodeResourcesData(
                    maxServices: 50,
                    currentServices: 12,
                    cpuUsage: 35.5,
                    capacityAvailable: true,
                ));
            }
        };

        $this->assertSame('stub', $provider->key());
        $this->assertSame('Stub Node Provider', $provider->label());
        $this->assertSame(ProviderOperationStatus::Success, $provider->testConnection($node)->status);
        $this->assertSame(ProviderOperationStatus::Success, $provider->sync($node)->status);
        $this->assertTrue($provider->getResources($node)->resources->capacityAvailable);
        $this->assertInstanceOf(NodeProviderInterface::class, $provider);
    }
}

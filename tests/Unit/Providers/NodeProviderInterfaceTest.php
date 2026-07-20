<?php

namespace Tests\Unit\Providers;

use Core\Providers\Contracts\NodeProviderInterface;
use PHPUnit\Framework\TestCase;

class NodeProviderInterfaceTest extends TestCase
{
    public function test_contract_can_be_implemented(): void
    {
        $node = [
            'id' => 1,
            'module' => 'pterodactyl',
            'name' => 'Node 01',
            'hostname' => 'node-01.example.test',
            'api_url' => 'https://panel.example.test',
            'credentials' => ['api_key' => 'secret'],
        ];

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

            public function testConnection(array $node): array
            {
                return [
                    'status' => 'success',
                    'message' => 'Connected to '.$node['hostname'],
                    'response' => [],
                ];
            }

            public function sync(array $node): array
            {
                return [
                    'status' => 'success',
                    'changes' => [],
                    'response' => ['node_id' => $node['id'] ?? null],
                ];
            }

            public function getResources(array $node): array
            {
                return [
                    'status' => 'success',
                    'resources' => [
                        'max_services' => 50,
                        'current_services' => 12,
                        'cpu_usage' => 35.5,
                        'capacity_available' => true,
                    ],
                    'response' => [],
                ];
            }
        };

        $this->assertSame('stub', $provider->key());
        $this->assertSame('Stub Node Provider', $provider->label());
        $this->assertSame('success', $provider->testConnection($node)['status']);
        $this->assertSame('success', $provider->sync($node)['status']);
        $this->assertTrue($provider->getResources($node)['resources']['capacity_available']);
        $this->assertInstanceOf(NodeProviderInterface::class, $provider);
    }
}

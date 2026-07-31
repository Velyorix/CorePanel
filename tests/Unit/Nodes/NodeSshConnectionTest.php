<?php

namespace Tests\Unit\Nodes;

use Core\Nodes\DataTransferObjects\NodeSshConnection;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Tests\TestCase;

class NodeSshConnectionTest extends TestCase
{
    public function test_prefers_ip_address_over_hostname_for_ssh_host(): void
    {
        $connection = NodeSshConnection::tryFromNodeRequest(NodeConnectionRequest::fromArray([
            'hostname' => 'label.local',
            'ip_address' => '203.0.113.10',
            'credentials' => [
                'ssh_username' => 'root',
                'ssh_password' => 'secret',
            ],
        ]));

        $this->assertNotNull($connection);
        $this->assertSame('203.0.113.10', $connection->host);
    }

    public function test_falls_back_to_api_url_host_when_ip_is_missing(): void
    {
        $connection = NodeSshConnection::tryFromNodeRequest(NodeConnectionRequest::fromArray([
            'hostname' => 'label.local',
            'api_url' => 'https://203.0.113.20:8006',
            'credentials' => [
                'ssh_username' => 'root',
                'ssh_password' => 'secret',
            ],
        ]));

        $this->assertNotNull($connection);
        $this->assertSame('203.0.113.20', $connection->host);
    }

    public function test_returns_null_when_ssh_secret_is_missing(): void
    {
        $connection = NodeSshConnection::tryFromNodeRequest(NodeConnectionRequest::fromArray([
            'hostname' => '203.0.113.10',
            'credentials' => [
                'ssh_username' => 'root',
            ],
        ]));

        $this->assertNull($connection);
    }
}

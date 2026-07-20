<?php

namespace Tests\Unit\Providers;

use Core\Products\Enums\BillingCycle;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeResourcesData;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Enums\ProviderOperationStatus;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProvisioningDtosTest extends TestCase
{
    public function test_provisioning_request_is_built_from_service(): void
    {
        $service = Service::factory()->make([
            'client_id' => 7,
            'product_id' => 3,
            'order_id' => 11,
            'order_item_id' => 22,
            'status' => ServiceStatus::Pending,
            'module' => 'pterodactyl',
            'billing_cycle' => BillingCycle::Monthly,
            'hostname' => 'game-01.example.test',
            'external_id' => 'ext-99',
            'ip_address' => '203.0.113.10',
            'node_id' => 5,
            'config_data' => ['ram' => '8'],
        ]);
        $service->id = 42;

        $node = NodeConnectionRequest::fromArray([
            'id' => 5,
            'hostname' => 'node-01.example.test',
            'module' => 'pterodactyl',
        ]);

        $request = ProvisioningRequest::fromService($service, $node);

        $this->assertSame(42, $request->serviceId);
        $this->assertSame(7, $request->clientId);
        $this->assertSame('pterodactyl', $request->module);
        $this->assertSame(ServiceStatus::Pending, $request->status);
        $this->assertSame('game-01.example.test', $request->hostname);
        $this->assertSame('ext-99', $request->externalId);
        $this->assertSame(5, $request->nodeId);
        $this->assertSame(['ram' => '8'], $request->configData);
        $this->assertSame('node-01.example.test', $request->node?->hostname);
    }

    public function test_provisioning_request_requires_module_on_service(): void
    {
        $service = new Service([
            'id' => 1,
            'client_id' => 1,
            'product_id' => 1,
            'status' => ServiceStatus::Pending,
            'module' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service module is required to build a provisioning request.');

        ProvisioningRequest::fromService($service);
    }

    public function test_node_connection_request_round_trips_array_shape(): void
    {
        $request = NodeConnectionRequest::fromArray([
            'id' => 9,
            'module' => 'proxmox',
            'name' => 'EU-01',
            'hostname' => 'eu-01.example.test',
            'ip_address' => '198.51.100.20',
            'api_url' => 'https://proxmox.example.test',
            'credentials' => ['token' => 'secret'],
            'max_services' => 100,
        ]);

        $this->assertSame([
            'id' => 9,
            'module' => 'proxmox',
            'name' => 'EU-01',
            'hostname' => 'eu-01.example.test',
            'ip_address' => '198.51.100.20',
            'api_url' => 'https://proxmox.example.test',
            'credentials' => ['token' => 'secret'],
            'max_services' => 100,
        ], $request->toArray());
    }

    public function test_provisioning_response_factories(): void
    {
        $success = ProvisioningResponse::success(
            externalId: 'ext-1',
            hostname: 'vm-01.example.test',
            ipAddress: '203.0.113.1',
            nodeId: 3,
            message: 'Provisioned',
            payload: ['job_id' => 'abc'],
        );

        $this->assertSame(ProviderOperationStatus::Success, $success->status);
        $this->assertTrue($success->isSuccessful());
        $this->assertSame('ext-1', $success->externalId);
        $this->assertSame('vm-01.example.test', $success->hostname);
        $this->assertSame(['job_id' => 'abc'], $success->payload);

        $failed = ProvisioningResponse::failed('Module unavailable');
        $this->assertSame(ProviderOperationStatus::Failed, $failed->status);
        $this->assertFalse($failed->isSuccessful());
    }

    public function test_node_resources_data_serializes_metrics(): void
    {
        $resources = NodeResourcesData::fromArray([
            'max_services' => 50,
            'current_services' => 12,
            'cpu_usage' => 35.5,
            'capacity_available' => true,
        ]);

        $this->assertSame([
            'max_services' => 50,
            'current_services' => 12,
            'cpu_usage' => 35.5,
            'capacity_available' => true,
        ], $resources->toArray());
    }
}

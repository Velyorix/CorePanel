<?php

namespace Tests\Unit\Providers;

use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\Enums\ProviderOperationStatus;
use Core\Providers\Services\ProviderRegistry;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Stubs\StubServerProvider;
use Core\Provisioning\Services\ProvisioningEngine;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StubProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.provisioning.stub.enabled' => true,
            'corepanel.provisioning.stub.key' => 'stub',
            'corepanel.provisioning.stub.label' => 'Stub Provider',
            'corepanel.provisioning.stub.register_node_provider' => true,
            'corepanel.provisioning.stub.fail_operations' => [],
        ]);
    }

    public function test_stub_providers_are_registered_when_enabled(): void
    {
        $registry = app(ProviderRegistry::class);
        $registry->flush();

        $registry->registerServer(app(StubServerProvider::class));
        $registry->registerNode(app(StubNodeProvider::class));

        $this->assertTrue($registry->hasServer('stub'));
        $this->assertTrue($registry->hasNode('stub'));
        $this->assertSame('Stub Provider', $registry->server('stub')->label());
    }

    public function test_stub_server_create_is_deterministic_and_idempotent(): void
    {
        $provider = app(StubServerProvider::class);

        $service = Service::factory()->make([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
            'hostname' => null,
            'external_id' => null,
        ]);
        $service->id = 42;

        $request = ProvisioningRequest::fromService($service);
        $first = $provider->create($request);
        $second = $provider->create(ProvisioningRequest::fromService(
            tap(clone $service, function (Service $clone) use ($first): void {
                $clone->external_id = $first->externalId;
            }),
        ));

        $this->assertSame(ProviderOperationStatus::Success, $first->status);
        $this->assertSame('stub-42', $first->externalId);
        $this->assertSame('service-42.stub.local', $first->hostname);
        $this->assertSame('10.255.0.43', $first->ipAddress);
        $this->assertSame('stub-42', $second->externalId);
    }

    public function test_stub_server_lifecycle_operations_succeed(): void
    {
        $provider = app(StubServerProvider::class);
        $service = Service::factory()->make([
            'module' => 'stub',
            'external_id' => 'stub-1',
            'status' => ServiceStatus::Active,
        ]);
        $service->id = 1;
        $request = ProvisioningRequest::fromService($service);

        $this->assertTrue($provider->suspend($request)->isSuccessful());
        $this->assertTrue($provider->unsuspend($request)->isSuccessful());
        $this->assertTrue($provider->reinstall($request)->isSuccessful());
        $this->assertTrue($provider->terminate($request)->isSuccessful());
    }

    public function test_stub_server_can_force_create_failure(): void
    {
        config(['corepanel.provisioning.stub.fail_operations' => ['create']]);

        $provider = app(StubServerProvider::class);
        $service = Service::factory()->make(['module' => 'stub', 'status' => ServiceStatus::Pending]);
        $service->id = 9;

        $response = $provider->create(ProvisioningRequest::fromService($service));

        $this->assertSame(ProviderOperationStatus::Failed, $response->status);
        $this->assertStringContainsString('forced create failure', (string) $response->message);
    }

    public function test_stub_node_provider_test_connection_and_resources(): void
    {
        $provider = app(StubNodeProvider::class);
        $node = NodeConnectionRequest::fromArray([
            'id' => 3,
            'hostname' => 'node-01.stub.local',
            'module' => 'stub',
            'max_services' => 25,
        ]);

        $connection = $provider->testConnection($node);
        $resources = $provider->getResources($node);
        $sync = $provider->sync($node);

        $this->assertSame(ProviderOperationStatus::Success, $connection->status);
        $this->assertTrue($resources->resources->capacityAvailable);
        $this->assertSame(25, $resources->resources->maxServices);
        $this->assertSame(ProviderOperationStatus::Success, $sync->status);
    }

    public function test_engine_provisions_service_with_registered_stub_provider(): void
    {
        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));

        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
            'hostname' => null,
            'external_id' => null,
        ]);

        $response = app(ProvisioningEngine::class)->provision($service);

        $this->assertSame(ProviderOperationStatus::Success, $response->status);

        $service->refresh();
        $this->assertSame(ServiceStatus::Active, $service->status);
        $this->assertSame('stub-'.$service->id, $service->external_id);
        $this->assertSame('service-'.$service->id.'.stub.local', $service->hostname);
        $this->assertNotNull($service->ip_address);
        $this->assertDatabaseHas('provider_resource_mappings', [
            'service_id' => $service->id,
            'module' => 'stub',
            'external_id' => 'stub-'.$service->id,
        ]);
    }
}

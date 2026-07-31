<?php

namespace Tests\Unit\Provisioning;

use Core\Provisioning\Events\ServiceProvisioned;
use Core\Provisioning\Events\ServiceProvisioningFailed;
use Core\Services\Models\Service;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisioningEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_provisioned_carries_service_and_external_id(): void
    {
        $service = Service::factory()->create();

        $event = new ServiceProvisioned($service, 'ext-42');

        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertTrue($event->service->is($service));
        $this->assertSame('ext-42', $event->externalId);
    }

    public function test_service_provisioning_failed_carries_service_and_reason(): void
    {
        $service = Service::factory()->create();

        $event = new ServiceProvisioningFailed($service, 'provider timeout');

        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertTrue($event->service->is($service));
        $this->assertSame('provider timeout', $event->reason);
    }
}

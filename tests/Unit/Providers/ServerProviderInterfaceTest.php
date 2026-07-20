<?php

namespace Tests\Unit\Providers;

use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Enums\ProviderOperationStatus;
use Core\Services\Models\Service;
use PHPUnit\Framework\TestCase;

class ServerProviderInterfaceTest extends TestCase
{
    public function test_contract_can_be_implemented(): void
    {
        $service = Service::factory()->make([
            'client_id' => 1,
            'product_id' => 2,
            'module' => 'pterodactyl',
        ]);
        $service->id = 10;

        $request = ProvisioningRequest::fromService($service);

        $provider = new class implements ServerProviderInterface
        {
            public function key(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'Stub Provider';
            }

            public function create(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success(
                    externalId: 'ext-'.$request->serviceId,
                    hostname: $request->hostname,
                );
            }

            public function suspend(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function unsuspend(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function terminate(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function reinstall(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }
        };

        $response = $provider->create($request);

        $this->assertSame('stub', $provider->key());
        $this->assertSame('Stub Provider', $provider->label());
        $this->assertSame(ProviderOperationStatus::Success, $response->status);
        $this->assertSame('ext-10', $response->externalId);
        $this->assertInstanceOf(ServerProviderInterface::class, $provider);
    }
}

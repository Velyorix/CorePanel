<?php

namespace Tests\Unit\Providers;

use Core\Providers\Services\ModulePermissionRegistrar;
use Core\Providers\Services\ProviderRegistry;
use Tests\TestCase;

class ProviderContainerBindingsTest extends TestCase
{
    public function test_provider_registry_is_registered_as_singleton(): void
    {
        $first = app(ProviderRegistry::class);
        $second = app(ProviderRegistry::class);

        $this->assertInstanceOf(ProviderRegistry::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_module_permission_registrar_is_registered_as_singleton(): void
    {
        $first = app(ModulePermissionRegistrar::class);
        $second = app(ModulePermissionRegistrar::class);

        $this->assertInstanceOf(ModulePermissionRegistrar::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_provider_registry_resolves_stub_provider_by_module_key(): void
    {
        $registry = app(ProviderRegistry::class);
        $registry->flush();

        $registry->registerServer(new class implements \Core\Providers\Contracts\ServerProviderInterface
        {
            public function key(): string
            {
                return 'stub-module';
            }

            public function label(): string
            {
                return 'Stub Module';
            }

            public function create(\Core\Providers\DataTransferObjects\ProvisioningRequest $request): \Core\Providers\DataTransferObjects\ProvisioningResponse
            {
                return \Core\Providers\DataTransferObjects\ProvisioningResponse::success();
            }

            public function suspend(\Core\Providers\DataTransferObjects\ProvisioningRequest $request): \Core\Providers\DataTransferObjects\ProvisioningResponse
            {
                return \Core\Providers\DataTransferObjects\ProvisioningResponse::success();
            }

            public function unsuspend(\Core\Providers\DataTransferObjects\ProvisioningRequest $request): \Core\Providers\DataTransferObjects\ProvisioningResponse
            {
                return \Core\Providers\DataTransferObjects\ProvisioningResponse::success();
            }

            public function terminate(\Core\Providers\DataTransferObjects\ProvisioningRequest $request): \Core\Providers\DataTransferObjects\ProvisioningResponse
            {
                return \Core\Providers\DataTransferObjects\ProvisioningResponse::success();
            }

            public function reinstall(\Core\Providers\DataTransferObjects\ProvisioningRequest $request): \Core\Providers\DataTransferObjects\ProvisioningResponse
            {
                return \Core\Providers\DataTransferObjects\ProvisioningResponse::success();
            }
        });

        $this->assertTrue($registry->hasServer('stub-module'));
        $this->assertSame('stub-module', $registry->server('stub-module')->key());

        $registry->flush();
    }
}

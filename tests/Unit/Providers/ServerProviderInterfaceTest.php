<?php

namespace Tests\Unit\Providers;

use Core\Providers\Contracts\ServerProviderInterface;
use Core\Services\Models\Service;
use PHPUnit\Framework\TestCase;

class ServerProviderInterfaceTest extends TestCase
{
    public function test_contract_can_be_implemented(): void
    {
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

            public function create(Service $service): array
            {
                return [
                    'status' => 'success',
                    'external_id' => 'ext-1',
                    'response' => ['service_id' => $service->id],
                ];
            }

            public function suspend(Service $service): array
            {
                return ['status' => 'success', 'response' => []];
            }

            public function unsuspend(Service $service): array
            {
                return ['status' => 'success', 'response' => []];
            }

            public function terminate(Service $service): array
            {
                return ['status' => 'success', 'response' => []];
            }

            public function reinstall(Service $service): array
            {
                return ['status' => 'success', 'response' => []];
            }
        };

        $this->assertSame('stub', $provider->key());
        $this->assertSame('Stub Provider', $provider->label());
        $this->assertInstanceOf(ServerProviderInterface::class, $provider);
    }
}

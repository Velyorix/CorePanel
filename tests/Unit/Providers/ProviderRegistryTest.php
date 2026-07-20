<?php

namespace Tests\Unit\Providers;

use Core\Billing\DataTransferObjects\PaymentContext;
use Core\Billing\DataTransferObjects\PaymentGatewayResult;
use Core\Billing\Models\Payment;
use Core\Providers\Contracts\NodeProviderInterface;
use Core\Providers\Contracts\PaymentGatewayInterface;
use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\NodeResourcesData;
use Core\Providers\DataTransferObjects\NodeResourcesResponse;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Exceptions\UnknownProviderException;
use Core\Providers\Services\ProviderRegistry;
use Core\Services\Models\Service;
use PHPUnit\Framework\TestCase;

class ProviderRegistryTest extends TestCase
{
    public function test_registers_and_resolves_server_provider_by_key(): void
    {
        $registry = new ProviderRegistry;
        $provider = $this->makeServerProvider('pterodactyl');

        $registry->registerServer($provider);

        $this->assertTrue($registry->hasServer('pterodactyl'));
        $this->assertSame($provider, $registry->server('pterodactyl'));
        $this->assertSame(['pterodactyl'], $registry->serverKeys());
    }

    public function test_registers_and_resolves_node_provider_by_key(): void
    {
        $registry = new ProviderRegistry;
        $provider = $this->makeNodeProvider('proxmox');

        $registry->registerNode($provider);

        $this->assertTrue($registry->hasNode('proxmox'));
        $this->assertSame($provider, $registry->node('proxmox'));
        $this->assertSame(['proxmox'], $registry->nodeKeys());
    }

    public function test_registers_and_resolves_payment_gateway_by_key(): void
    {
        $registry = new ProviderRegistry;
        $provider = $this->makePaymentGateway('stripe');

        $registry->registerPaymentGateway($provider);

        $this->assertTrue($registry->hasPaymentGateway('stripe'));
        $this->assertSame($provider, $registry->paymentGateway('stripe'));
        $this->assertSame(['stripe'], $registry->paymentGatewayKeys());
    }

    public function test_flush_clears_all_provider_types(): void
    {
        $registry = new ProviderRegistry;
        $registry->registerServer($this->makeServerProvider('srv'));
        $registry->registerNode($this->makeNodeProvider('node'));
        $registry->registerPaymentGateway($this->makePaymentGateway('gw'));

        $registry->flush();

        $this->assertSame([], $registry->serverKeys());
        $this->assertSame([], $registry->nodeKeys());
        $this->assertSame([], $registry->paymentGatewayKeys());
    }

    public function test_unknown_provider_resolution_throws(): void
    {
        $registry = new ProviderRegistry;

        $this->expectException(UnknownProviderException::class);
        $this->expectExceptionMessage('Unknown server provider [missing].');

        $registry->server('missing');
    }

    public function test_unknown_node_provider_resolution_throws(): void
    {
        $registry = new ProviderRegistry;

        $this->expectException(UnknownProviderException::class);
        $this->expectExceptionMessage('Unknown node provider [missing].');

        $registry->node('missing');
    }

    public function test_unknown_payment_gateway_resolution_throws(): void
    {
        $registry = new ProviderRegistry;

        $this->expectException(UnknownProviderException::class);
        $this->expectExceptionMessage('Unknown payment gateway provider [missing].');

        $registry->paymentGateway('missing');
    }

    public function test_has_methods_return_false_for_unknown_keys(): void
    {
        $registry = new ProviderRegistry;

        $this->assertFalse($registry->hasServer('missing'));
        $this->assertFalse($registry->hasNode('missing'));
        $this->assertFalse($registry->hasPaymentGateway('missing'));
    }

    public function test_reregistering_overwrites_existing_provider(): void
    {
        $registry = new ProviderRegistry;
        $first = $this->makeServerProvider('pterodactyl');
        $second = $this->makeServerProvider('pterodactyl');

        $registry->registerServer($first);
        $registry->registerServer($second);

        $this->assertSame($second, $registry->server('pterodactyl'));
        $this->assertCount(1, $registry->allServers());
    }

    public function test_all_methods_return_registered_providers(): void
    {
        $registry = new ProviderRegistry;
        $server = $this->makeServerProvider('srv');
        $node = $this->makeNodeProvider('node');
        $gateway = $this->makePaymentGateway('gw');

        $registry->registerServer($server);
        $registry->registerNode($node);
        $registry->registerPaymentGateway($gateway);

        $this->assertSame([$server], $registry->allServers());
        $this->assertSame([$node], $registry->allNodes());
        $this->assertSame([$gateway], $registry->allPaymentGateways());
    }

    private function makeServerProvider(string $key): ServerProviderInterface
    {
        return new class($key) implements ServerProviderInterface
        {
            public function __construct(private readonly string $keyValue)
            {
            }

            public function key(): string
            {
                return $this->keyValue;
            }

            public function label(): string
            {
                return strtoupper($this->keyValue);
            }

            public function create(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
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
    }

    private function makeNodeProvider(string $key): NodeProviderInterface
    {
        return new class($key) implements NodeProviderInterface
        {
            public function __construct(private readonly string $keyValue)
            {
            }

            public function key(): string
            {
                return $this->keyValue;
            }

            public function label(): string
            {
                return strtoupper($this->keyValue);
            }

            public function testConnection(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::success();
            }

            public function sync(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::success();
            }

            public function getResources(NodeConnectionRequest $node): NodeResourcesResponse
            {
                return NodeResourcesResponse::success(new NodeResourcesData);
            }
        };
    }

    private function makePaymentGateway(string $key): PaymentGatewayInterface
    {
        return new class($key) implements PaymentGatewayInterface
        {
            public function __construct(private readonly string $keyValue)
            {
            }

            public function key(): string
            {
                return $this->keyValue;
            }

            public function label(): string
            {
                return strtoupper($this->keyValue);
            }

            public function charge(Payment $payment, PaymentContext $context): PaymentGatewayResult
            {
                return PaymentGatewayResult::pending();
            }

            public function refund(Payment $payment, string $amount): PaymentGatewayResult
            {
                return PaymentGatewayResult::refunded();
            }

            public function validate(Payment $payment): PaymentGatewayResult
            {
                return PaymentGatewayResult::completed();
            }
        };
    }
}

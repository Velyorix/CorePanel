<?php

namespace Tests\Unit\Providers;

use Core\Billing\DataTransferObjects\PaymentContext;
use Core\Billing\DataTransferObjects\PaymentGatewayResult;
use Core\Billing\Models\Payment;
use Core\Providers\Contracts\NodeProviderInterface;
use Core\Providers\Contracts\PaymentGatewayInterface;
use Core\Providers\Contracts\ServerProviderInterface;
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

            public function create(Service $service): array
            {
                return ['status' => 'success', 'response' => []];
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

            public function testConnection(array $node): array
            {
                return ['status' => 'success', 'response' => []];
            }

            public function sync(array $node): array
            {
                return ['status' => 'success', 'response' => []];
            }

            public function getResources(array $node): array
            {
                return ['status' => 'success', 'resources' => [], 'response' => []];
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

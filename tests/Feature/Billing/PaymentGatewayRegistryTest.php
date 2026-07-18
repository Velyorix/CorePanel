<?php

namespace Tests\Feature\Billing;

use Core\Billing\Exceptions\UnknownPaymentGatewayException;
use Core\Billing\Services\PaymentGatewayRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\TestCase;

class PaymentGatewayRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(PaymentGatewayRegistry::class),
            app(PaymentGatewayRegistry::class),
        );
    }

    public function test_register_and_resolve_gateways(): void
    {
        $registry = app(PaymentGatewayRegistry::class);
        $gateway = new FakePaymentGateway;

        $registry->register($gateway);

        $this->assertTrue($registry->has('fake'));
        $this->assertSame($gateway, $registry->get('fake'));
        $this->assertSame(['fake'], $registry->keys());
        $this->assertCount(1, $registry->all());
    }

    public function test_get_unknown_gateway_throws(): void
    {
        $this->expectException(UnknownPaymentGatewayException::class);
        $this->expectExceptionMessage('Unknown payment gateway [missing].');

        app(PaymentGatewayRegistry::class)->get('missing');
    }

    public function test_register_overwrites_same_key(): void
    {
        $registry = app(PaymentGatewayRegistry::class);
        $first = new FakePaymentGateway(label: 'First');
        $second = new FakePaymentGateway(label: 'Second');

        $registry->register($first);
        $registry->register($second);

        $this->assertSame('Second', $registry->get('fake')->label());
        $this->assertCount(1, $registry->all());
    }
}

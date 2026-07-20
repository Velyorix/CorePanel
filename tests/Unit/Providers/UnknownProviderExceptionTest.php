<?php

namespace Tests\Unit\Providers;

use Core\Providers\Exceptions\UnknownProviderException;
use PHPUnit\Framework\TestCase;

class UnknownProviderExceptionTest extends TestCase
{
    public function test_for_server_builds_expected_message(): void
    {
        $exception = UnknownProviderException::forServer('pterodactyl');

        $this->assertSame('Unknown server provider [pterodactyl].', $exception->getMessage());
        $this->assertInstanceOf(UnknownProviderException::class, $exception);
    }

    public function test_for_node_builds_expected_message(): void
    {
        $exception = UnknownProviderException::forNode('proxmox');

        $this->assertSame('Unknown node provider [proxmox].', $exception->getMessage());
    }

    public function test_for_payment_gateway_builds_expected_message(): void
    {
        $exception = UnknownProviderException::forPaymentGateway('stripe');

        $this->assertSame('Unknown payment gateway provider [stripe].', $exception->getMessage());
    }
}

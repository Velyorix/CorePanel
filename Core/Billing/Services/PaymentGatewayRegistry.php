<?php

namespace Core\Billing\Services;

use Core\Billing\Contracts\PaymentGateway;
use Core\Billing\Exceptions\UnknownPaymentGatewayException;

/**
 * In-memory registry of payment gateways (modules register at boot).
 */
class PaymentGatewayRegistry
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    public function register(PaymentGateway $gateway): void
    {
        $this->gateways[$gateway->key()] = $gateway;
    }

    public function has(string $key): bool
    {
        return isset($this->gateways[$key]);
    }

    public function get(string $key): PaymentGateway
    {
        if (! isset($this->gateways[$key])) {
            throw UnknownPaymentGatewayException::forKey($key);
        }

        return $this->gateways[$key];
    }

    /**
     * @return list<PaymentGateway>
     */
    public function all(): array
    {
        return array_values($this->gateways);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->gateways);
    }
}

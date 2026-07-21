<?php

namespace Tests\Support\Billing;

use Core\Billing\Contracts\RegistersPaymentGateways;
use Core\Billing\Services\GatewayManager;

/**
 * Plugin-style hook used in Feature tests before a real plugins framework exists.
 */
class FakePluginGatewayRegistrar implements RegistersPaymentGateways
{
    public function __construct(
        private readonly string $key = 'plugin_fake',
        private readonly string $label = 'Plugin Fake Gateway',
    ) {
    }

    public function registerPaymentGateways(GatewayManager $gateways): void
    {
        $gateways->register(new FakePaymentGateway(
            key: $this->key,
            label: $this->label,
        ));
    }
}

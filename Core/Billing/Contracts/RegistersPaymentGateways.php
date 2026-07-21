<?php

namespace Core\Billing\Contracts;

use Core\Billing\Services\GatewayManager;

/**
 * Hook for plugins (and optional module helpers) to register payment gateways at boot.
 */
interface RegistersPaymentGateways
{
    public function registerPaymentGateways(GatewayManager $gateways): void;
}

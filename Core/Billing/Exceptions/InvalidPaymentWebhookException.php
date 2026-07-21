<?php

namespace Core\Billing\Exceptions;

use RuntimeException;

class InvalidPaymentWebhookException extends RuntimeException
{
    public static function invalidSignature(string $gatewayKey): self
    {
        return new self("Invalid webhook signature for payment gateway [{$gatewayKey}].");
    }
}

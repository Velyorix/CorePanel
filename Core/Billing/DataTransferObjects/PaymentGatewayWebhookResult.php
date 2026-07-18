<?php

namespace Core\Billing\DataTransferObjects;

use Core\Billing\Enums\PaymentStatus;

final readonly class PaymentGatewayWebhookResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $paymentId,
        public PaymentStatus $status,
        public ?string $transactionId = null,
        public ?string $gatewayReference = null,
        public array $raw = [],
    ) {
    }
}

<?php

namespace Core\Billing\DataTransferObjects;

final readonly class PaymentContext
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $returnUrl = null,
        public ?string $cancelUrl = null,
        public ?string $clientIp = null,
        public array $metadata = [],
    ) {
    }
}

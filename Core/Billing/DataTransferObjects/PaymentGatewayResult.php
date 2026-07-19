<?php

namespace Core\Billing\DataTransferObjects;

use Core\Billing\Enums\PaymentStatus;

final readonly class PaymentGatewayResult
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $transactionId = null,
        public ?string $gatewayReference = null,
        public ?string $redirectUrl = null,
        public ?string $message = null,
    ) {
    }

    public static function pending(
        ?string $transactionId = null,
        ?string $gatewayReference = null,
        ?string $redirectUrl = null,
        ?string $message = null,
    ): self {
        return new self(
            PaymentStatus::Pending,
            $transactionId,
            $gatewayReference,
            $redirectUrl,
            $message,
        );
    }

    public static function completed(
        ?string $transactionId = null,
        ?string $gatewayReference = null,
        ?string $message = null,
    ): self {
        return new self(
            PaymentStatus::Completed,
            $transactionId,
            $gatewayReference,
            null,
            $message,
        );
    }

    public static function failed(?string $message = null): self
    {
        return new self(PaymentStatus::Failed, message: $message);
    }

    public static function refunded(
        ?string $transactionId = null,
        ?string $gatewayReference = null,
        ?string $message = null,
    ): self {
        return new self(
            PaymentStatus::Refunded,
            $transactionId,
            $gatewayReference,
            null,
            $message,
        );
    }
}

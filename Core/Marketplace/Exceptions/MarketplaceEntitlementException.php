<?php

namespace Core\Marketplace\Exceptions;

use Core\Marketplace\DataTransferObjects\MarketplaceInstallDecision;
use Core\Marketplace\Enums\MarketplaceEntitlementReason;
use RuntimeException;
use Throwable;

class MarketplaceEntitlementException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly MarketplaceInstallDecision $decision,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromDecision(MarketplaceInstallDecision $decision): self
    {
        $message = $decision->message
            ?? match ($decision->reason) {
                MarketplaceEntitlementReason::MissingLicense => 'A valid license is required before installing marketplace packages.',
                MarketplaceEntitlementReason::MissingEntitlement => 'This marketplace package is not included in the current license entitlements.',
                MarketplaceEntitlementReason::UnsupportedProductType => 'This marketplace product type cannot be installed.',
                default => 'Marketplace installation is not allowed for this package.',
            };

        return new self($message, $decision);
    }
}

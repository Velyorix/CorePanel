<?php

namespace Core\Marketplace\Exceptions;

use Core\Marketplace\DataTransferObjects\MarketplaceCompatibilityDecision;
use RuntimeException;
use Throwable;

class MarketplaceCompatibilityException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly MarketplaceCompatibilityDecision $decision,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromDecision(MarketplaceCompatibilityDecision $decision): self
    {
        $message = $decision->message
            ?? 'This marketplace package is not compatible with the current CorePanel version.';

        return new self($message, $decision);
    }
}

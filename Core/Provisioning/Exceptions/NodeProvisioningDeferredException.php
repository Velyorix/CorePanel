<?php

namespace Core\Provisioning\Exceptions;

use RuntimeException;

class NodeProvisioningDeferredException extends RuntimeException
{
    public function __construct(
        public readonly int $retryAfterSeconds,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function overloadedPool(?string $groupKey = null, ?int $delaySeconds = null): self
    {
        $delaySeconds = max(5, $delaySeconds ?? (int) config('corepanel.nodes.overload.queue_delay_seconds', 60));
        $suffix = $groupKey !== null && $groupKey !== ''
            ? " for node group [{$groupKey}]"
            : '';

        return new self(
            retryAfterSeconds: $delaySeconds,
            message: "All eligible nodes are overloaded{$suffix}. Provisioning has been delayed.",
        );
    }
}

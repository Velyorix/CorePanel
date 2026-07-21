<?php

namespace Core\Provisioning\Exceptions;

use RuntimeException;

class NoEligibleNodeException extends RuntimeException
{
    public static function forGroup(string $groupKey, ?string $module = null): self
    {
        $moduleSuffix = $module !== null && $module !== ''
            ? " and module [{$module}]"
            : '';

        return new self(
            "No eligible node found for node group [{$groupKey}]{$moduleSuffix}.",
        );
    }

    public static function forMissingGroup(string $reference): self
    {
        return new self("Configured node group [{$reference}] could not be resolved.");
    }
}

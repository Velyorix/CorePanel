<?php

namespace Core\Automation\DataTransferObjects;

use Core\Automation\Models\AutomationLog;

/**
 * Result of claiming an automation idempotency key.
 */
final readonly class IdempotencyClaim
{
    public function __construct(
        public AutomationLog $log,
        public bool $isNew,
    ) {
    }
}

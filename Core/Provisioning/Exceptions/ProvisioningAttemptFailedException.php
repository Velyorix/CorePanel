<?php

namespace Core\Provisioning\Exceptions;

use RuntimeException;

/**
 * Transient provisioning failure that should be retried by the queue worker.
 */
class ProvisioningAttemptFailedException extends RuntimeException
{
    public static function fromMessage(?string $message = null): self
    {
        return new self($message ?: 'Provisioning attempt failed.');
    }
}

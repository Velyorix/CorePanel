<?php

namespace Core\Provisioning\Exceptions;

use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use RuntimeException;

class ProvisioningException extends RuntimeException
{
    public static function missingModule(Service $service): self
    {
        return new self("Service [{$service->id}] has no module key for provisioning.");
    }

    public static function invalidStatus(Service $service): self
    {
        $status = $service->status instanceof ServiceStatus
            ? $service->status->value
            : (string) $service->status;

        return new self("Service [{$service->id}] cannot be provisioned from status [{$status}].");
    }
}

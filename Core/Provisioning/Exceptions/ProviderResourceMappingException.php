<?php

namespace Core\Provisioning\Exceptions;

use RuntimeException;

class ProviderResourceMappingException extends RuntimeException
{
    public static function conflict(string $module, string $externalId, string $resourceType): self
    {
        return new self(
            "Provider resource [{$module}:{$resourceType}:{$externalId}] is already mapped to another service.",
        );
    }

    public static function missingModule(int $serviceId): self
    {
        return new self("Cannot map provider resource for service [{$serviceId}] without a module key.");
    }

    public static function missingExternalId(int $serviceId): self
    {
        return new self("Cannot map provider resource for service [{$serviceId}] without an external_id.");
    }
}

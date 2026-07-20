<?php

namespace Core\Providers\Exceptions;

use InvalidArgumentException;

class InvalidModulePermissionException extends InvalidArgumentException
{
    public static function notModuleScoped(string $permission): self
    {
        return new self("Module permission [{$permission}] must start with [module.].");
    }

    public static function invalidFormat(string $permission): self
    {
        return new self("Module permission [{$permission}] must follow module.{module}.{resource}.{action}.");
    }

    public static function moduleKeyMismatch(string $permission, string $moduleKey): self
    {
        return new self("Module permission [{$permission}] does not belong to module [{$moduleKey}].");
    }

    public static function invalidManifestEntry(mixed $entry): self
    {
        return new self('Module manifest permissions must be strings or objects with a [name] field.');
    }
}

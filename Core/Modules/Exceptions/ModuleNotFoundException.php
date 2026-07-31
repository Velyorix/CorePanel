<?php

namespace Core\Modules\Exceptions;

use RuntimeException;

class ModuleNotFoundException extends RuntimeException
{
    public static function withKey(string $key): self
    {
        return new self("Module [{$key}] was not found in the modules path.");
    }
}

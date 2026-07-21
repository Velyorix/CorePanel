<?php

namespace Core\Modules\Exceptions;

use RuntimeException;

class ModuleSignatureException extends RuntimeException
{
    public static function missing(string $moduleKey): self
    {
        return new self("Module [{$moduleKey}] is missing a checksum/signature and signature verification is required.");
    }

    public static function mismatch(string $moduleKey): self
    {
        return new self("Module [{$moduleKey}] failed integrity verification (checksum/signature mismatch).");
    }

    public static function unreadable(string $moduleKey, string $path): self
    {
        return new self("Module [{$moduleKey}] package files could not be hashed at [{$path}].");
    }
}

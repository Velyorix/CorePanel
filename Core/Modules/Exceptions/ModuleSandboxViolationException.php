<?php

namespace Core\Modules\Exceptions;

use RuntimeException;

class ModuleSandboxViolationException extends RuntimeException
{
    public static function coreTableAccess(string $moduleKey, string $table, string $sql): self
    {
        return new self(
            "Module [{$moduleKey}] is not allowed to access Core table [{$table}] directly. "
            .'Use the module host API instead. SQL: '.$sql,
        );
    }

    public static function foreignModuleTable(string $moduleKey, string $table): self
    {
        return new self(
            "Module [{$moduleKey}] is not allowed to access table [{$table}] owned by another module.",
        );
    }

    public static function forbiddenConfig(string $moduleKey, string $key): self
    {
        return new self(
            "Module [{$moduleKey}] is not allowed to read config key [{$key}].",
        );
    }

    public static function hostUnavailable(string $moduleKey): self
    {
        return new self("Module host API is not available for module [{$moduleKey}].");
    }
}

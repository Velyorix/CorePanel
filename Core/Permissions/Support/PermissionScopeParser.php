<?php

namespace Core\Permissions\Support;

class PermissionScopeParser
{
    public const SCOPE_OWN = 'own';

    public const SCOPE_ANY = 'any';

    public static function base(string $permission): string
    {
        if (str_ends_with($permission, '.'.self::SCOPE_OWN)) {
            return substr($permission, 0, -strlen('.'.self::SCOPE_OWN));
        }

        if (str_ends_with($permission, '.'.self::SCOPE_ANY)) {
            return substr($permission, 0, -strlen('.'.self::SCOPE_ANY));
        }

        return $permission;
    }

    public static function isOwnScoped(string $permission): bool
    {
        return str_ends_with($permission, '.'.self::SCOPE_OWN);
    }

    public static function isAnyScoped(string $permission): bool
    {
        return str_ends_with($permission, '.'.self::SCOPE_ANY);
    }

    public static function ownPermission(string $basePermission): string
    {
        return self::base($basePermission).'.'.self::SCOPE_OWN;
    }

    public static function anyPermission(string $basePermission): string
    {
        return self::base($basePermission).'.'.self::SCOPE_ANY;
    }
}

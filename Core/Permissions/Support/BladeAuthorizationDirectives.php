<?php

namespace Core\Permissions\Support;

use Core\Auth\Models\User;
use Core\Permissions\Services\PermissionService;
use Illuminate\Support\Facades\Blade;

class BladeAuthorizationDirectives
{
    public static function register(): void
    {
        Blade::if('permission', function (string $permission, mixed $subject = null): bool {
            $user = auth()->user();

            if (! $user instanceof User) {
                return false;
            }

            return app(PermissionService::class)->userCan($user, $permission, $subject);
        });

        Blade::if('anypermission', function (string ...$permissions): bool {
            $user = auth()->user();

            if (! $user instanceof User || $permissions === []) {
                return false;
            }

            return app(PermissionService::class)->userHasAnyPermission($user, $permissions);
        });

        Blade::if('allpermissions', function (string ...$permissions): bool {
            $user = auth()->user();

            if (! $user instanceof User || $permissions === []) {
                return false;
            }

            return app(PermissionService::class)->userHasAllPermissions($user, $permissions);
        });

        Blade::if('role', function (string $role): bool {
            $user = auth()->user();

            if (! $user instanceof User) {
                return false;
            }

            return app(PermissionService::class)->userHasRole($user, $role);
        });

        Blade::if('anyrole', function (string ...$roles): bool {
            $user = auth()->user();

            if (! $user instanceof User || $roles === []) {
                return false;
            }

            return app(PermissionService::class)->userHasAnyRole($user, $roles);
        });
    }
}

<?php

namespace Core\Permissions\Models;

use Core\Permissions\Services\PermissionService;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot for user_roles — invalidates RBAC cache when membership changes.
 */
class UserRole extends Pivot
{
    public $incrementing = false;

    protected $table = 'user_roles';

    protected static function booted(): void
    {
        $invalidate = static function (self $pivot): void {
            $userId = (int) $pivot->getAttribute('user_id');

            if ($userId > 0) {
                app(PermissionService::class)->forgetUser($userId);
            }
        };

        static::created($invalidate);
        static::deleted($invalidate);
    }
}

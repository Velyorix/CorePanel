<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Permissions\Services\PermissionService;

abstract class BasePolicy
{
    public function __construct(
        protected readonly PermissionService $permissionService,
    ) {
    }

    protected function allows(User $user, string $permission): bool
    {
        return $this->permissionService->userHasPermission($user, $permission);
    }
}

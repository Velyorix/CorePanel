<?php

namespace Core\Permissions\Services;

use Core\Auth\Models\User;
use Core\Permissions\Enums\PermissionOverrideEffect;
use Core\Permissions\Models\Permission;
use InvalidArgumentException;

class UserPermissionService
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {
    }

    public function grant(User $user, string|Permission $permission): void
    {
        $this->setEffect($user, $permission, PermissionOverrideEffect::Grant);
    }

    public function deny(User $user, string|Permission $permission): void
    {
        $this->setEffect($user, $permission, PermissionOverrideEffect::Deny);
    }

    public function revoke(User $user, string|Permission $permission): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $permissionModel = $this->resolvePermission($permission);

        $user->permissionOverrides()->detach($permissionModel->id);
        $this->permissionService->forgetUser($user);
    }

    public function setEffect(
        User $user,
        string|Permission $permission,
        PermissionOverrideEffect $effect,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $permissionModel = $this->resolvePermission($permission);

        $user->permissionOverrides()->syncWithoutDetaching([
            $permissionModel->id => ['effect' => $effect->value],
        ]);

        $this->permissionService->forgetUser($user);
    }

    /**
     * @return list<string>
     */
    public function grantsForUser(User $user): array
    {
        return $this->overridesForUser($user, PermissionOverrideEffect::Grant);
    }

    /**
     * @return list<string>
     */
    public function deniesForUser(User $user): array
    {
        return $this->overridesForUser($user, PermissionOverrideEffect::Deny);
    }

    /**
     * @return list<string>
     */
    private function overridesForUser(User $user, PermissionOverrideEffect $effect): array
    {
        $user->loadMissing('permissionOverrides');

        return $user->permissionOverrides
            ->filter(fn (Permission $permission): bool => $permission->pivot->effect === $effect->value)
            ->pluck('name')
            ->values()
            ->all();
    }

    private function resolvePermission(string|Permission $permission): Permission
    {
        if ($permission instanceof Permission) {
            return $permission;
        }

        $model = Permission::query()->where('name', $permission)->first();

        if ($model === null) {
            throw new InvalidArgumentException("Unknown permission [{$permission}].");
        }

        return $model;
    }

    private function isEnabled(): bool
    {
        return (bool) config('corepanel.rbac.user_overrides.enabled', true);
    }
}

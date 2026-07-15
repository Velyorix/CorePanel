<?php

namespace Core\Permissions\Services;

use Core\Auth\Models\User;
use Core\Permissions\Models\Role;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    /**
     * @return list<string>
     */
    public function permissionsForUser(User $user): array
    {
        return $this->resolveAccessForUser($user)['permissions'];
    }

    /**
     * @return list<string>
     */
    public function rolesForUser(User $user): array
    {
        return $this->resolveAccessForUser($user)['roles'];
    }

    public function userHasPermission(User $user, string $permission): bool
    {
        return in_array($permission, $this->permissionsForUser($user), true);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function userHasAnyPermission(User $user, array $permissions): bool
    {
        $granted = $this->permissionsForUser($user);

        foreach ($permissions as $permission) {
            if (in_array($permission, $granted, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $permissions
     */
    public function userHasAllPermissions(User $user, array $permissions): bool
    {
        $granted = $this->permissionsForUser($user);

        foreach ($permissions as $permission) {
            if (! in_array($permission, $granted, true)) {
                return false;
            }
        }

        return true;
    }

    public function userHasRole(User $user, string $role): bool
    {
        return in_array($role, $this->rolesForUser($user), true);
    }

    /**
     * @param  list<string>  $roles
     */
    public function userHasAnyRole(User $user, array $roles): bool
    {
        $assigned = $this->rolesForUser($user);

        foreach ($roles as $role) {
            if (in_array($role, $assigned, true)) {
                return true;
            }
        }

        return false;
    }

    public function forgetUser(User|int $user): void
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        $this->cache()->forget($this->cacheKey((int) $userId));
    }

    public function forgetRole(Role $role): void
    {
        $userIds = $role->users()->pluck('users.id');

        foreach ($userIds as $userId) {
            $this->forgetUser((int) $userId);
        }
    }

    public function forgetAll(): void
    {
        // Tag-based invalidation is not used yet; callers should forget targeted users/roles.
    }

    /**
     * @return array{roles: list<string>, permissions: list<string>}
     */
    private function resolveAccessForUser(User $user): array
    {
        if (! $this->cacheEnabled()) {
            return $this->loadAccessForUser($user);
        }

        /** @var array{roles: list<string>, permissions: list<string>} $access */
        $access = $this->cache()->remember(
            $this->cacheKey($user->getKey()),
            $this->cacheTtlSeconds(),
            fn (): array => $this->loadAccessForUser($user),
        );

        return $access;
    }

    /**
     * @return array{roles: list<string>, permissions: list<string>}
     */
    private function loadAccessForUser(User $user): array
    {
        $user->loadMissing('roles.permissions');

        $roles = $user->roles
            ->pluck('name')
            ->unique()
            ->values()
            ->all();

        $permissions = $user->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();

        return [
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }

    private function cache(): CacheRepository
    {
        return Cache::store($this->cacheStore());
    }

    private function cacheKey(int $userId): string
    {
        return $this->cachePrefix().'.user.'.$userId;
    }

    private function cacheEnabled(): bool
    {
        return (bool) config('corepanel.rbac.cache.enabled', true);
    }

    private function cacheStore(): string
    {
        return (string) config('corepanel.rbac.cache.store', 'redis');
    }

    private function cachePrefix(): string
    {
        return (string) config('corepanel.rbac.cache.prefix', 'corepanel.rbac');
    }

    private function cacheTtlSeconds(): int
    {
        return (int) config('corepanel.rbac.cache.ttl_seconds', 3600);
    }
}

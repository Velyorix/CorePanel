<?php

namespace Core\Permissions\Services;

use Core\Permissions\Models\Permission;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

class PermissionRegistry
{
    /**
     * @return list<string>
     */
    public function all(): array
    {
        if (! $this->cacheEnabled()) {
            return $this->loadAll();
        }

        /** @var list<string> $permissions */
        $permissions = $this->cache()->remember(
            $this->cacheKey(),
            $this->cacheTtlSeconds(),
            fn (): array => $this->loadAll(),
        );

        return $permissions;
    }

    public function contains(string $permission): bool
    {
        return in_array($permission, $this->all(), true);
    }

    public function forget(): void
    {
        $this->cache()->forget($this->cacheKey());
    }

    /**
     * @return list<string>
     */
    private function loadAll(): array
    {
        return Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    private function cache(): CacheRepository
    {
        return Cache::store($this->cacheStore());
    }

    private function cacheKey(): string
    {
        return $this->cachePrefix().'.permissions.all';
    }

    private function cacheEnabled(): bool
    {
        return (bool) config('corepanel.rbac.permissions_registry.cache_enabled', true);
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
        return (int) config('corepanel.rbac.permissions_registry.ttl_seconds', 3600);
    }
}

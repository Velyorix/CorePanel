<?php

namespace Core\Marketplace\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Local cache for marketplace catalogue / versions API payloads.
 */
class MarketplaceCatalogCache
{
    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function remember(string $key, callable $callback): mixed
    {
        if (! $this->enabled()) {
            return $callback();
        }

        return $this->store()->remember(
            $this->fullKey($key),
            now()->addSeconds($this->ttlSeconds()),
            $callback,
        );
    }

    public function forget(string $key): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->store()->forget($this->fullKey($key));
    }

    /**
     * Invalidate every catalogue entry by bumping the cache generation.
     */
    public function flush(): void
    {
        if (! $this->enabled()) {
            return;
        }

        $generationKey = $this->prefix().'.generation';
        $current = (int) $this->store()->get($generationKey, 1);
        $this->store()->forever($generationKey, $current + 1);
    }

    public function productsKey(array $query): string
    {
        ksort($query);

        return 'products.'.hash('sha256', (string) json_encode($query));
    }

    public function productKey(string $slug): string
    {
        return 'product.'.$this->normalizeSlug($slug);
    }

    public function versionsKey(string $slug): string
    {
        return 'versions.'.$this->normalizeSlug($slug);
    }

    public function versionKey(string $slug, string $version): string
    {
        return 'version.'.$this->normalizeSlug($slug).'.'.$this->normalizeSlug($version);
    }

    public function enabled(): bool
    {
        return (bool) config('corepanel.marketplace.cache.enabled', true);
    }

    public function ttlSeconds(): int
    {
        return max(0, (int) config('corepanel.marketplace.cache.ttl_seconds', 3600));
    }

    private function store(): CacheRepository
    {
        return Cache::store($this->storeName());
    }

    private function storeName(): string
    {
        return (string) config('corepanel.marketplace.cache.store', 'redis');
    }

    private function prefix(): string
    {
        return (string) config('corepanel.marketplace.cache.prefix', 'corepanel.marketplace');
    }

    private function generation(): int
    {
        return max(1, (int) $this->store()->get($this->prefix().'.generation', 1));
    }

    private function fullKey(string $key): string
    {
        return $this->prefix().'.v'.$this->generation().'.'.$key;
    }

    private function normalizeSlug(string $value): string
    {
        return strtolower(trim($value));
    }
}

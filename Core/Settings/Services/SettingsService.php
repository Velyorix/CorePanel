<?php

namespace Core\Settings\Services;

use Core\Settings\Models\Setting;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Central runtime settings: get/set, typed values, cache, encryption.
 */
class SettingsService
{
    private const AUTOLOAD_CACHE_KEY = '__autoload__';

    /**
     * @var list<string>
     */
    private const TYPES = ['string', 'boolean', 'integer', 'json', 'encrypted'];

    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->resolveRow($key);

        if ($row === null) {
            return $default;
        }

        return $this->decode($row['value'], $row['type']);
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key, $default);

        if ($value === null) {
            return $default;
        }

        return is_string($value) ? $value : (string) $value;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return (bool) $value;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>|null  $default
     * @return array<string, mixed>|null
     */
    public function getJson(string $key, ?array $default = null): ?array
    {
        $value = $this->get($key, $default);

        if ($value === null) {
            return $default;
        }

        return is_array($value) ? $value : $default;
    }

    public function getEncrypted(string $key): ?string
    {
        $row = $this->resolveRow($key);

        if ($row === null || blank($row['value'])) {
            return null;
        }

        if ($row['type'] !== 'encrypted') {
            return is_string($row['value']) ? $row['value'] : null;
        }

        try {
            return Crypt::decryptString((string) $row['value']);
        } catch (DecryptException) {
            return null;
        }
    }

    public function set(
        string $key,
        mixed $value,
        string $type = 'string',
        bool $autoload = false,
    ): void {
        if (! $this->tableReady()) {
            return;
        }

        $type = $this->assertType($type);

        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $this->encode($value, $type),
                'type' => $type,
                'autoload' => $autoload,
                'updated_at' => now(),
            ],
        );

        $this->forgetCacheKeys([$key, self::AUTOLOAD_CACHE_KEY]);
    }

    public function setEncrypted(string $key, string $value, bool $autoload = false): void
    {
        $this->set($key, $value, 'encrypted', $autoload);
    }

    public function forget(string $key): void
    {
        if (! $this->tableReady()) {
            return;
        }

        Setting::query()->where('key', $key)->delete();
        $this->forgetCacheKeys([$key, self::AUTOLOAD_CACHE_KEY]);
    }

    public function has(string $key): bool
    {
        return $this->resolveRow($key) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function allAutoloaded(): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        $rows = $this->remember(self::AUTOLOAD_CACHE_KEY, function (): array {
            return Setting::query()
                ->where('autoload', true)
                ->get(['key', 'value', 'type'])
                ->mapWithKeys(fn (Setting $setting): array => [
                    $setting->key => [
                        'value' => $setting->value,
                        'type' => $setting->type,
                    ],
                ])
                ->all();
        });

        $decoded = [];

        foreach ($rows as $key => $row) {
            $decoded[$key] = $this->decode($row['value'] ?? null, (string) ($row['type'] ?? 'string'));
        }

        return $decoded;
    }

    public function flushCache(): void
    {
        if (! $this->cacheEnabled()) {
            return;
        }

        // Prefix-wide flush is store-dependent; clear known keys via autoload + no-op safety.
        $this->cache()->forget($this->cacheKey(self::AUTOLOAD_CACHE_KEY));

        if (! $this->tableReady()) {
            return;
        }

        Setting::query()
            ->pluck('key')
            ->each(fn (string $key): bool => $this->cache()->forget($this->cacheKey($key)));
    }

    /**
     * @return array{value: string|null, type: string}|null
     */
    private function resolveRow(string $key): ?array
    {
        if (! $this->tableReady()) {
            return null;
        }

        return $this->remember($key, function () use ($key): ?array {
            $setting = Setting::query()->where('key', $key)->first(['value', 'type']);

            if ($setting === null) {
                return null;
            }

            return [
                'value' => $setting->value,
                'type' => $setting->type ?: 'string',
            ];
        });
    }

    private function encode(mixed $value, string $type): ?string
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => $value === null ? null : (string) (int) $value,
            'json' => $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR),
            'encrypted' => Crypt::encryptString((string) $value),
            default => $value === null ? null : (string) $value,
        };
    }

    private function decode(?string $value, string $type): mixed
    {
        if ($value === null) {
            return match ($type) {
                'boolean' => false,
                'integer' => 0,
                'json' => null,
                default => null,
            };
        }

        return match ($type) {
            'boolean' => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'integer' => (int) $value,
            'json' => $this->decodeJson($value),
            'encrypted' => $this->decryptOrNull($value),
            default => $value,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $value): ?array
    {
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function decryptOrNull(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    private function assertType(string $type): string
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported setting type [{$type}].");
        }

        return $type;
    }

    /**
     * @template TCacheValue
     *
     * @param  callable(): TCacheValue  $callback
     * @return TCacheValue
     */
    private function remember(string $key, callable $callback): mixed
    {
        if (! $this->cacheEnabled()) {
            return $callback();
        }

        return $this->cache()->remember(
            $this->cacheKey($key),
            $this->cacheTtl(),
            $callback,
        );
    }

    /**
     * @param  list<string>  $keys
     */
    private function forgetCacheKeys(array $keys): void
    {
        if (! $this->cacheEnabled()) {
            return;
        }

        foreach ($keys as $key) {
            $this->cache()->forget($this->cacheKey($key));
        }
    }

    private function cache(): CacheRepository
    {
        return Cache::store($this->cacheStore());
    }

    private function cacheEnabled(): bool
    {
        return (bool) config('corepanel.settings.cache.enabled', true);
    }

    private function cacheStore(): string
    {
        return (string) config('corepanel.settings.cache.store', config('cache.default', 'file'));
    }

    private function cacheTtl(): int
    {
        return max(1, (int) config('corepanel.settings.cache.ttl_seconds', 3600));
    }

    private function cacheKey(string $key): string
    {
        $prefix = trim((string) config('corepanel.settings.cache.prefix', 'corepanel.settings'));

        return $prefix === '' ? $key : $prefix.'.'.$key;
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Throwable) {
            return false;
        }
    }
}

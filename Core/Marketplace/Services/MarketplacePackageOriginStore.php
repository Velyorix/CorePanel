<?php

namespace Core\Marketplace\Services;

use Core\Settings\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks which local packages were installed from the marketplace.
 */
class MarketplacePackageOriginStore
{
    public const SETTING_KEY = 'marketplace.package_origins';

    /**
     * @return list<array{product_type: string, package_key: string, slug: string, sku: string|null}>
     */
    public function all(): array
    {
        if (! $this->ready()) {
            return [];
        }

        $value = Setting::query()->where('key', self::SETTING_KEY)->value('value');

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $origins = [];

        foreach ($decoded as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $type = strtolower(trim((string) ($entry['product_type'] ?? '')));
            $key = trim((string) ($entry['package_key'] ?? ''));
            $slug = trim((string) ($entry['slug'] ?? ''));

            if (! in_array($type, ['module', 'theme'], true) || $key === '' || $slug === '') {
                continue;
            }

            $origins[] = [
                'product_type' => $type,
                'package_key' => $key,
                'slug' => $slug,
                'sku' => isset($entry['sku']) && is_string($entry['sku']) && $entry['sku'] !== ''
                    ? $entry['sku']
                    : null,
            ];
        }

        return $origins;
    }

    public function remember(string $productType, string $packageKey, string $slug, ?string $sku = null): void
    {
        $productType = strtolower(trim($productType));
        $packageKey = trim($packageKey);
        $slug = trim($slug);

        if (! in_array($productType, ['module', 'theme'], true) || $packageKey === '' || $slug === '') {
            throw new \InvalidArgumentException('Marketplace package origin is invalid.');
        }

        $origins = $this->all();
        $replaced = false;

        foreach ($origins as $index => $origin) {
            if ($origin['product_type'] === $productType && $origin['package_key'] === $packageKey) {
                $origins[$index] = [
                    'product_type' => $productType,
                    'package_key' => $packageKey,
                    'slug' => $slug,
                    'sku' => $sku,
                ];
                $replaced = true;
                break;
            }
        }

        if (! $replaced) {
            $origins[] = [
                'product_type' => $productType,
                'package_key' => $packageKey,
                'slug' => $slug,
                'sku' => $sku,
            ];
        }

        $this->write($origins);
    }

    public function forget(string $productType, string $packageKey): void
    {
        $productType = strtolower(trim($productType));
        $packageKey = trim($packageKey);

        $origins = array_values(array_filter(
            $this->all(),
            static fn (array $origin): bool => ! (
                $origin['product_type'] === $productType
                && $origin['package_key'] === $packageKey
            ),
        ));

        $this->write($origins);
    }

    /**
     * @param  list<array{product_type: string, package_key: string, slug: string, sku: string|null}>  $origins
     */
    private function write(array $origins): void
    {
        if (! $this->ready()) {
            return;
        }

        Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            [
                'value' => json_encode(array_values($origins), JSON_THROW_ON_ERROR),
                'type' => 'json',
                'autoload' => false,
                'updated_at' => now(),
            ],
        );
    }

    private function ready(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Throwable) {
            return false;
        }
    }
}

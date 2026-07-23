<?php

namespace Core\Marketplace\Services;

use Core\Marketplace\DataTransferObjects\MarketplaceAvailableUpdate;
use Core\Marketplace\DataTransferObjects\MarketplaceProduct;
use Core\Marketplace\DataTransferObjects\MarketplaceProductVersion;
use Core\Marketplace\DataTransferObjects\MarketplaceUpdateCheckResult;
use Core\Marketplace\Exceptions\MarketplaceApiException;
use Core\Modules\Services\ModuleManager;
use Core\Themes\Services\ThemeManager;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Detects available marketplace updates for locally installed packages.
 */
class MarketplaceUpdateChecker
{
    public function __construct(
        private readonly MarketplacePackageOriginStore $origins,
        private readonly MarketplaceClient $marketplace,
        private readonly MarketplaceCompatibilityGuard $compatibility,
        private readonly ModuleManager $modules,
        private readonly ThemeManager $themes,
    ) {
    }

    public function check(): MarketplaceUpdateCheckResult
    {
        $updates = [];
        $checked = 0;

        foreach ($this->origins->all() as $origin) {
            $checked++;

            try {
                $update = $this->checkOrigin($origin);
            } catch (MarketplaceApiException $exception) {
                Log::warning('Marketplace update check failed for package.', [
                    'slug' => $origin['slug'],
                    'package_key' => $origin['package_key'],
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($update !== null) {
                $updates[] = $update;
            }
        }

        $result = new MarketplaceUpdateCheckResult(
            updates: $updates,
            checkedCount: $checked,
            checkedAt: now()->toIso8601String(),
        );

        $this->store($result);

        return $result;
    }

    public function latestResult(): ?MarketplaceUpdateCheckResult
    {
        $payload = $this->cache()->get($this->resultCacheKey());

        if (! is_array($payload)) {
            return null;
        }

        return MarketplaceUpdateCheckResult::fromArray($payload);
    }

    /**
     * @return list<MarketplaceAvailableUpdate>
     */
    public function availableUpdates(): array
    {
        return $this->latestResult()?->updates ?? [];
    }

    /**
     * @param  array{product_type: string, package_key: string, slug: string, sku: string|null}  $origin
     */
    private function checkOrigin(array $origin): ?MarketplaceAvailableUpdate
    {
        $installedVersion = $this->installedVersion($origin['product_type'], $origin['package_key']);

        if ($installedVersion === null) {
            return null;
        }

        $product = $this->marketplace->getProduct($origin['slug']);
        $versions = $this->marketplace->listVersions($origin['slug'])->versions;
        $candidate = $this->selectUpdateCandidate($product, $versions, $installedVersion);

        if ($candidate === null) {
            return null;
        }

        return new MarketplaceAvailableUpdate(
            productType: $origin['product_type'],
            packageKey: $origin['package_key'],
            marketplaceSlug: $origin['slug'],
            installedVersion: $installedVersion,
            availableVersion: $candidate['version']->version,
            compatible: $candidate['compatible'],
            sku: $origin['sku'] ?? $product->sku,
        );
    }

    /**
     * @param  list<MarketplaceProductVersion>  $versions
     * @return array{version: MarketplaceProductVersion, compatible: bool}|null
     */
    private function selectUpdateCandidate(
        MarketplaceProduct $product,
        array $versions,
        string $installedVersion,
    ): ?array {
        $installed = $this->normalizeVersion($installedVersion);
        $bestCompatible = null;
        $bestAny = null;

        foreach ($versions as $version) {
            if (! $version->hasArchive) {
                continue;
            }

            $available = $this->normalizeVersion($version->version);

            if (version_compare($available, $installed, '<=')) {
                continue;
            }

            $compatible = $this->compatibility->isCompatible($version, $product);

            if ($compatible && ($bestCompatible === null || version_compare($available, $this->normalizeVersion($bestCompatible->version), '>'))) {
                $bestCompatible = $version;
            }

            if ($bestAny === null || version_compare($available, $this->normalizeVersion($bestAny->version), '>')) {
                $bestAny = $version;
            }
        }

        if ($bestCompatible !== null) {
            return ['version' => $bestCompatible, 'compatible' => true];
        }

        if ($bestAny !== null) {
            return ['version' => $bestAny, 'compatible' => false];
        }

        return null;
    }

    private function installedVersion(string $productType, string $packageKey): ?string
    {
        if ($productType === 'module') {
            $installation = $this->modules->installation($packageKey);

            if ($installation !== null && is_string($installation->version) && $installation->version !== '') {
                return $installation->version;
            }

            $manifest = $this->modules->get($packageKey);

            return $manifest?->version;
        }

        $theme = $this->themes->discover()->first(
            static fn ($descriptor): bool => $descriptor->key === $packageKey,
        );

        return $theme?->version;
    }

    private function store(MarketplaceUpdateCheckResult $result): void
    {
        $ttl = max(60, (int) config('corepanel.marketplace.updates.cache_ttl_seconds', 86400));

        $this->cache()->put($this->resultCacheKey(), $result->toArray(), now()->addSeconds($ttl));
    }

    private function cache(): CacheRepository
    {
        return Cache::store((string) config('corepanel.marketplace.cache.store', 'redis'));
    }

    private function resultCacheKey(): string
    {
        $prefix = (string) config('corepanel.marketplace.cache.prefix', 'corepanel.marketplace');

        return $prefix.'.updates.result';
    }

    private function normalizeVersion(string $version): string
    {
        $version = ltrim(trim($version), 'vV');

        if (preg_match('/^\d+\.\d+\.\d+/', $version, $matches) === 1) {
            return $matches[0];
        }

        return $version === '' ? '0.0.0' : $version;
    }
}

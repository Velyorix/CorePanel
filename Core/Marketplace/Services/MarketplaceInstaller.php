<?php

namespace Core\Marketplace\Services;

use Core\Marketplace\DataTransferObjects\MarketplaceInstallResult;
use Core\Marketplace\DataTransferObjects\MarketplaceProduct;
use Core\Marketplace\DataTransferObjects\MarketplaceProductVersion;
use Core\Marketplace\Exceptions\MarketplaceInstallException;
use Core\Modules\Services\ModuleManager;
use Core\Themes\Services\ThemeManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Downloads and installs marketplace modules / themes into the local CMS.
 */
class MarketplaceInstaller
{
    public function __construct(
        private readonly MarketplaceClient $marketplace,
        private readonly MarketplaceEntitlementGuard $entitlements,
        private readonly MarketplacePackageDownloader $downloader,
        private readonly MarketplacePackageExtractor $extractor,
        private readonly ModuleManager $modules,
        private readonly ThemeManager $themes,
    ) {
    }

    /**
     * Install a marketplace product (latest version when $version is null).
     */
    public function install(string $productSlug, ?string $version = null, bool $enable = false): MarketplaceInstallResult
    {
        $product = $this->entitlements->assertCanInstallSlug($productSlug);
        $resolvedVersion = $this->resolveVersion($product, $version);

        if (! $resolvedVersion->hasArchive) {
            throw MarketplaceInstallException::archiveMissing($product->slug, $resolvedVersion->version);
        }

        $descriptor = $this->marketplace->requestDownload($product->slug, $resolvedVersion->version);
        $archivePath = $this->downloader->download($descriptor);

        $staging = $this->stagingPath($product->slug, $resolvedVersion->version);
        File::deleteDirectory($staging);
        File::ensureDirectoryExists($staging);

        try {
            $manifestName = $product->isTheme() ? 'theme.json' : 'module.json';
            $packageRoot = $this->extractor->extract($archivePath, $staging, $manifestName);
            $destination = $this->installExtractedPackage($product, $packageRoot);

            if ($product->isModule()) {
                $this->modules->discover(refresh: true);
                $manifest = $this->modules->findOrFail($this->moduleKeyFromPath($destination));
                $this->modules->install($manifest->key, enable: $enable);

                $result = new MarketplaceInstallResult(
                    productSlug: $product->slug,
                    productType: $product->productType,
                    version: $resolvedVersion->version,
                    packageKey: $manifest->key,
                    installedPath: $destination,
                    enabled: $enable,
                );
            } else {
                $this->themes->discover(refresh: true);
                $descriptorTheme = $this->themes->findOrFail($this->themeKeyFromPath($destination));

                if ($enable) {
                    $this->themes->activate($descriptorTheme->key);
                }

                $result = new MarketplaceInstallResult(
                    productSlug: $product->slug,
                    productType: $product->productType,
                    version: $resolvedVersion->version,
                    packageKey: $descriptorTheme->key,
                    installedPath: $destination,
                    enabled: $enable,
                );
            }

            $this->marketplace->forgetProductCache($product->slug);

            return $result;
        } finally {
            if (is_file($archivePath)) {
                @unlink($archivePath);
            }

            File::deleteDirectory($staging);
        }
    }

    private function resolveVersion(MarketplaceProduct $product, ?string $version): MarketplaceProductVersion
    {
        if (is_string($version) && trim($version) !== '') {
            return $this->marketplace->getVersion($product->slug, trim($version));
        }

        $latest = $this->marketplace->listVersions($product->slug)->latest();

        if ($latest === null) {
            throw MarketplaceInstallException::archiveMissing($product->slug, 'latest');
        }

        return $latest;
    }

    private function installExtractedPackage(MarketplaceProduct $product, string $packageRoot): string
    {
        $destinationRoot = $product->isTheme()
            ? (string) config('corepanel.themes.path', base_path('Themes'))
            : (string) config('corepanel.modules.path', base_path('Modules'));

        File::ensureDirectoryExists($destinationRoot);

        $directoryName = basename($packageRoot);
        $destination = $destinationRoot.DIRECTORY_SEPARATOR.$directoryName;

        if (is_dir($destination)) {
            throw MarketplaceInstallException::packageAlreadyInstalled($destination);
        }

        if (! File::copyDirectory($packageRoot, $destination)) {
            throw MarketplaceInstallException::downloadFailed(
                'Unable to copy extracted marketplace package to ['.$destination.'].',
            );
        }

        return $destination;
    }

    private function moduleKeyFromPath(string $path): string
    {
        $manifestPath = $path.DIRECTORY_SEPARATOR.'module.json';
        $contents = File::get($manifestPath);
        $data = json_decode($contents, true);

        if (! is_array($data) || ! isset($data['name']) || ! is_string($data['name']) || trim($data['name']) === '') {
            throw MarketplaceInstallException::missingManifest('module');
        }

        return trim($data['name']);
    }

    private function themeKeyFromPath(string $path): string
    {
        $manifestPath = $path.DIRECTORY_SEPARATOR.'theme.json';
        $contents = File::get($manifestPath);
        $data = json_decode($contents, true);

        if (! is_array($data) || ! isset($data['name']) || ! is_string($data['name']) || trim($data['name']) === '') {
            throw MarketplaceInstallException::missingManifest('theme');
        }

        return trim($data['name']);
    }

    private function stagingPath(string $slug, string $version): string
    {
        $base = (string) config('corepanel.marketplace.install.temp_path', storage_path('app/marketplace/tmp'));

        return $base.DIRECTORY_SEPARATOR.'extract_'.Str::slug($slug).'_'.Str::slug($version).'_'.uniqid('', true);
    }
}

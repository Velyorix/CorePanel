<?php

namespace Core\Services\Services;

use Core\Products\Enums\ProductModuleCapability;
use Core\Services\Contracts\ModuleAccessLinkProvider;
use Core\Services\DataTransferObjects\ServiceAccessLinks;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;

/**
 * Resolves external panel / web console access links for a service.
 */
class ServiceAccessService
{
    public function __construct(
        private readonly ServiceConfigService $configs,
        private readonly ModuleAccessLinkProvider $modules,
    ) {
    }

    public function for(Service $service): ServiceAccessLinks
    {
        $service = $service->fresh(['product']) ?? $service;
        $config = $this->configs->get($service);

        $panelUrl = $this->firstUrl([
            $this->configUrl($config, 'panel_url'),
            $this->modules->panelUrl($service),
        ]);

        $consoleUrl = $this->firstUrl([
            $this->configUrl($config, 'console_url'),
            $this->modules->consoleUrl($service),
        ]);

        return new ServiceAccessLinks(
            panelUrl: $panelUrl,
            consoleUrl: $consoleUrl,
            canOpenPanel: $this->canOpen(
                $service,
                $panelUrl,
                ProductModuleCapability::ServerPanel,
            ),
            canOpenConsole: $this->canOpen(
                $service,
                $consoleUrl,
                ProductModuleCapability::ServerConsole,
            ),
        );
    }

    private function canOpen(Service $service, ?string $url, ProductModuleCapability $capability): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        if ($service->status !== ServiceStatus::Active) {
            return false;
        }

        return $this->passesCapabilityGate($service, $capability);
    }

    private function passesCapabilityGate(Service $service, ProductModuleCapability $capability): bool
    {
        $product = $service->product;

        if ($product === null) {
            return true;
        }

        $required = $product->requiredCapabilities();

        if ($required === []) {
            return true;
        }

        return $product->requiresCapability($capability);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function configUrl(array $config, string $key): ?string
    {
        $candidates = [
            data_get($config, "access.{$key}"),
            data_get($config, "credentials.{$key}"),
            data_get($config, $key),
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeUrl($candidate);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @param  list<?string>  $candidates
     */
    private function firstUrl(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeUrl($candidate);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $value;
    }
}

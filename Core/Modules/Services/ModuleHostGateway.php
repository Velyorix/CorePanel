<?php

namespace Core\Modules\Services;

use Core\Modules\Contracts\ModuleHostApi;
use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Exceptions\ModuleSandboxViolationException;
use Core\Providers\DataTransferObjects\ModulePermissionManifest;
use Core\Providers\Services\ModulePermissionRegistrar;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

class ModuleHostGateway implements ModuleHostApi
{
    public function __construct(
        private readonly string $moduleKey,
        private readonly ModuleManifest $manifest,
        private readonly ModuleSandbox $sandbox,
        private readonly ModulePermissionRegistrar $permissions,
        private readonly ?ModuleHookRegistry $hookRegistry = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function moduleKey(): string
    {
        return $this->moduleKey;
    }

    public function coreVersion(): string
    {
        return (string) config('corepanel.version', '0.0.0');
    }

    public function config(string $key, mixed $default = null): mixed
    {
        if (! $this->isAllowedConfigKey($key)) {
            throw ModuleSandboxViolationException::forbiddenConfig($this->moduleKey, $key);
        }

        return config($key, $default);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $context = array_merge(['module' => $this->moduleKey], $context);
        $logger = $this->logger ?? Log::getFacadeRoot();

        match (strtolower($level)) {
            'debug' => $logger->debug($message, $context),
            'info' => $logger->info($message, $context),
            'notice' => $logger->notice($message, $context),
            'warning', 'warn' => $logger->warning($message, $context),
            'error' => $logger->error($message, $context),
            'critical' => $logger->critical($message, $context),
            'alert' => $logger->alert($message, $context),
            'emergency' => $logger->emergency($message, $context),
            default => $logger->info($message, $context),
        };
    }

    public function registerPermissions(array $permissions = []): int
    {
        $entries = $permissions !== [] ? $permissions : $this->manifest->permissions;

        if ($entries === []) {
            return 0;
        }

        return $this->sandbox->bypass(function () use ($entries): int {
            $manifest = ModulePermissionManifest::fromArray($this->moduleKey, [
                'name' => $this->moduleKey,
                'permissions' => $entries,
            ]);

            $this->permissions->register($manifest);

            return count($manifest->permissions);
        });
    }

    public function registerHook(string $hook, callable $callback, int $priority = 10): string
    {
        return $this->hookRegistry()->registerHook($hook, $callback, $priority, $this->moduleKey);
    }

    public function registerFilter(string $filter, callable $callback, int $priority = 10): string
    {
        return $this->hookRegistry()->registerFilter($filter, $callback, $priority, $this->moduleKey);
    }

    public function listenEvent(string $event, callable $callback, int $priority = 10): string
    {
        return $this->hookRegistry()->listenEvent($event, $callback, $priority, $this->moduleKey);
    }

    private function hookRegistry(): ModuleHookRegistry
    {
        if ($this->hookRegistry === null) {
            throw ModuleSandboxViolationException::hostUnavailable($this->moduleKey);
        }

        return $this->hookRegistry;
    }

    private function isAllowedConfigKey(string $key): bool
    {
        $allowed = [
            'corepanel.version',
            'corepanel.name',
            'corepanel.modules.path',
            'corepanel.modules.sandbox',
            'app.name',
            'app.env',
            'app.timezone',
        ];

        foreach ($allowed as $prefix) {
            if ($key === $prefix || str_starts_with($key, $prefix.'.')) {
                return true;
            }
        }

        $moduleConfigPrefix = 'modules.'.$this->moduleKey;

        return $key === $moduleConfigPrefix || str_starts_with($key, $moduleConfigPrefix.'.');
    }
}

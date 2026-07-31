<?php

namespace Core\Modules\Services;

use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Exceptions\ModuleBootstrapException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProviderRegistrar
{
    /** @var array<string, list<string>> */
    private array $registered = [];

    public function __construct(
        private readonly Application $app,
        private readonly ModuleSandbox $sandbox,
    ) {
    }

    /**
     * Register Laravel service providers declared by the module manifest.
     *
     * @return list<string> Newly registered provider class names
     */
    public function register(ModuleManifest $manifest): array
    {
        $registeredNow = [];

        foreach ($manifest->providers as $providerClass) {
            if ($this->isRegistered($manifest->key, $providerClass)) {
                continue;
            }

            if (! class_exists($providerClass)) {
                throw ModuleBootstrapException::providerMissing($manifest->key, $providerClass);
            }

            if (! is_subclass_of($providerClass, ServiceProvider::class)) {
                throw ModuleBootstrapException::invalidProvider($manifest->key, $providerClass);
            }

            $this->sandbox->run($manifest->key, function () use ($providerClass): void {
                $this->app->register($providerClass);
            });

            $this->registered[$manifest->key] ??= [];
            $this->registered[$manifest->key][] = $providerClass;
            $registeredNow[] = $providerClass;
        }

        return $registeredNow;
    }

    public function isRegistered(string $moduleKey, string $providerClass): bool
    {
        return in_array($providerClass, $this->registered[$moduleKey] ?? [], true);
    }

    /**
     * @return list<string>
     */
    public function providersFor(string $moduleKey): array
    {
        return $this->registered[$moduleKey] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return $this->registered;
    }

    /**
     * Forget tracking for a module (Laravel cannot fully unregister providers).
     */
    public function forget(string $moduleKey): void
    {
        unset($this->registered[$moduleKey]);
    }
}

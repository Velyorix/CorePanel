<?php

namespace Core\Modules\Services;

use Core\Modules\Contracts\ModuleHostApi;
use Core\Modules\Contracts\ModuleInterface;
use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Exceptions\ModuleBootstrapException;
use Core\Modules\Support\AbstractModule;
use Core\Providers\Services\ModulePermissionRegistrar;
use Illuminate\Contracts\Container\Container;

class ModuleFactory
{
    public function __construct(
        private readonly Container $container,
        private readonly ModuleSandbox $sandbox,
        private readonly ModulePermissionRegistrar $permissions,
    ) {
    }

    public function make(ModuleManifest $manifest): ?ModuleInterface
    {
        $class = $manifest->moduleClass;

        if ($class === null) {
            return null;
        }

        if (! class_exists($class)) {
            throw ModuleBootstrapException::classMissing($manifest->key, $class);
        }

        $host = $this->makeHost($manifest);
        $module = $this->resolve($class, $manifest, $host);

        if (! $module instanceof ModuleInterface) {
            throw ModuleBootstrapException::invalidInstance($manifest->key, $class);
        }

        return $module;
    }

    public function makeHost(ModuleManifest $manifest): ModuleHostApi
    {
        return new ModuleHostGateway(
            moduleKey: $manifest->key,
            manifest: $manifest,
            sandbox: $this->sandbox,
            permissions: $this->permissions,
        );
    }

    private function resolve(string $class, ModuleManifest $manifest, ModuleHostApi $host): mixed
    {
        if (is_subclass_of($class, AbstractModule::class)) {
            return $this->container->make($class, [
                'manifest' => $manifest,
                'host' => $host,
            ]);
        }

        if ($this->container->bound($class)) {
            return $this->container->make($class);
        }

        return $this->container->make($class, [
            'manifest' => $manifest,
            'host' => $host,
        ]);
    }
}

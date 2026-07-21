<?php

namespace Core\Modules\Services;

use Core\Modules\Contracts\ModuleInterface;
use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Exceptions\ModuleBootstrapException;
use Core\Modules\Support\AbstractModule;
use Illuminate\Contracts\Container\Container;

class ModuleFactory
{
    public function __construct(
        private readonly Container $container,
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

        $module = $this->resolve($class, $manifest);

        if (! $module instanceof ModuleInterface) {
            throw ModuleBootstrapException::invalidInstance($manifest->key, $class);
        }

        return $module;
    }

    private function resolve(string $class, ModuleManifest $manifest): mixed
    {
        if (is_subclass_of($class, AbstractModule::class)) {
            return $this->container->make($class, ['manifest' => $manifest]);
        }

        if ($this->container->bound($class)) {
            return $this->container->make($class);
        }

        return $this->container->make($class, ['manifest' => $manifest]);
    }
}

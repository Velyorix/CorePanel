<?php

namespace Core\Modules\Support;

use Illuminate\Support\ServiceProvider;

/**
 * Base ServiceProvider for module packages declared in module.json "providers".
 */
abstract class AbstractModuleServiceProvider extends ServiceProvider
{
    /**
     * Stable module key this provider belongs to.
     */
    abstract public function moduleKey(): string;

    /**
     * Absolute path to the module package root (directory containing module.json).
     */
    protected function modulePath(?string $path = null): string
    {
        $root = $this->resolveModuleRoot();

        if ($path === null || $path === '') {
            return $root;
        }

        return $root.DIRECTORY_SEPARATOR.ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    protected function loadModuleRoutes(?string $file = null): void
    {
        $routesDirectory = $this->modulePath('routes');

        if (! is_dir($routesDirectory)) {
            return;
        }

        $files = $file !== null
            ? [$routesDirectory.DIRECTORY_SEPARATOR.$file]
            : (glob($routesDirectory.DIRECTORY_SEPARATOR.'*.php') ?: []);

        foreach ($files as $routeFile) {
            if (! is_file($routeFile)) {
                continue;
            }

            $this->loadRoutesFrom($routeFile);
        }
    }

    protected function loadModuleViews(string $namespace): void
    {
        $viewsPath = $this->modulePath('resources/views');

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, $namespace);
        }
    }

    protected function loadModuleMigrations(): void
    {
        $migrationsPath = $this->modulePath('database/migrations');

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    private function resolveModuleRoot(): string
    {
        $reflector = new \ReflectionClass($this);
        $providerFile = $reflector->getFileName();

        if ($providerFile === false) {
            return base_path('Modules');
        }

        $directory = dirname($providerFile);

        while ($directory !== dirname($directory)) {
            if (is_file($directory.DIRECTORY_SEPARATOR.'module.json')) {
                return $directory;
            }

            $directory = dirname($directory);
        }

        return dirname($providerFile, 2);
    }
}

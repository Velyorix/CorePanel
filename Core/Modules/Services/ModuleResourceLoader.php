<?php

namespace Core\Modules\Services;

use Core\Modules\DataTransferObjects\ModuleLoadedResources;
use Core\Modules\DataTransferObjects\ModuleManifest;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;

class ModuleResourceLoader
{
    /** @var array<string, ModuleLoadedResources> */
    private array $loaded = [];

    public function __construct(
        private readonly Application $app,
        private readonly ModuleSandbox $sandbox,
    ) {
    }

    public function load(ModuleManifest $manifest): ModuleLoadedResources
    {
        if (isset($this->loaded[$manifest->key])) {
            return $this->loaded[$manifest->key];
        }

        $resources = $this->sandbox->run(
            $manifest->key,
            fn (): ModuleLoadedResources => $this->loadResources($manifest),
        );

        $this->loaded[$manifest->key] = $resources;

        return $resources;
    }

    public function resourcesFor(string $moduleKey): ?ModuleLoadedResources
    {
        return $this->loaded[$moduleKey] ?? null;
    }

    public function forget(string $moduleKey): void
    {
        $resources = $this->loaded[$moduleKey] ?? null;

        if ($resources?->viewsNamespace !== null && $this->app->bound('view')) {
            $finder = $this->app->make('view')->getFinder();

            if (method_exists($finder, 'forgetNamespace')) {
                $finder->forgetNamespace($resources->viewsNamespace);
            }
        }

        unset($this->loaded[$moduleKey]);
    }

    /**
     * @return array<string, ModuleLoadedResources>
     */
    public function all(): array
    {
        return $this->loaded;
    }

    private function loadResources(ModuleManifest $manifest): ModuleLoadedResources
    {
        $routeFiles = [];
        $views = false;
        $migrations = false;
        $viewsNamespace = null;
        $migrationsPath = null;

        if ($this->shouldLoad('routes')) {
            $routeFiles = $this->loadRoutes($manifest);
        }

        if ($this->shouldLoad('views')) {
            [$views, $viewsNamespace] = $this->loadViews($manifest);
        }

        if ($this->shouldLoad('migrations')) {
            [$migrations, $migrationsPath] = $this->loadMigrations($manifest);
        }

        return new ModuleLoadedResources(
            routeFiles: $routeFiles,
            views: $views,
            migrations: $migrations,
            viewsNamespace: $viewsNamespace,
            migrationsPath: $migrationsPath,
        );
    }

    /**
     * @return list<string>
     */
    private function loadRoutes(ModuleManifest $manifest): array
    {
        $routesPath = $manifest->path.DIRECTORY_SEPARATOR.'routes';

        if (! is_dir($routesPath)) {
            return [];
        }

        $loaded = [];
        $files = glob($routesPath.DIRECTORY_SEPARATOR.'*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $basename = basename($file);
            $middleware = $this->middlewareForRouteFile($basename);

            Route::middleware($middleware)
                ->name('module.'.$manifest->key.'.')
                ->group($file);

            $loaded[] = $basename;
        }

        if ($loaded !== []) {
            $routes = Route::getRoutes();

            if (method_exists($routes, 'refreshNameLookups')) {
                $routes->refreshNameLookups();
            }

            if (method_exists($routes, 'refreshActionLookups')) {
                $routes->refreshActionLookups();
            }
        }

        return $loaded;
    }

    /**
     * @return array{0: bool, 1: ?string}
     */
    private function loadViews(ModuleManifest $manifest): array
    {
        $viewsPath = $manifest->path.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views';

        if (! is_dir($viewsPath)) {
            return [false, null];
        }

        $namespace = $manifest->key;
        $this->app->make('view')->addNamespace($namespace, $viewsPath);

        return [true, $namespace];
    }

    /**
     * @return array{0: bool, 1: ?string}
     */
    private function loadMigrations(ModuleManifest $manifest): array
    {
        $migrationsPath = $manifest->path.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';

        if (! is_dir($migrationsPath)) {
            return [false, null];
        }

        $register = function () use ($migrationsPath): void {
            $this->app->make('migrator')->path($migrationsPath);
        };

        if ($this->app->resolved('migrator')) {
            $register();
        } else {
            $this->app->afterResolving('migrator', function () use ($register): void {
                $register();
            });
        }

        return [true, $migrationsPath];
    }

    /**
     * @return list<string>
     */
    private function middlewareForRouteFile(string $basename): array
    {
        $map = config('corepanel.modules.resources.route_middleware', [
            'web.php' => ['web'],
            'admin.php' => ['admin'],
            'client.php' => ['client'],
            'api.php' => ['api'],
        ]);

        if (! is_array($map)) {
            return ['web'];
        }

        $middleware = $map[$basename] ?? ['web'];

        return array_values(array_filter(
            (array) $middleware,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        )) ?: ['web'];
    }

    private function shouldLoad(string $resource): bool
    {
        return (bool) config("corepanel.modules.resources.{$resource}", true);
    }
}

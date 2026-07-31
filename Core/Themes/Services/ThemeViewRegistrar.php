<?php

namespace Core\Themes\Services;

use Core\Modules\Services\ModuleManager;
use Core\Themes\DataTransferObjects\ThemeDescriptor;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\View\FileViewFinder;

/**
 * Registers theme view override paths (theme → module → core resolution).
 */
class ThemeViewRegistrar
{
    /** @var list<string> */
    private array $prependedCorePaths = [];

    /** @var array<string, list<string>> */
    private array $prependedModulePaths = [];

    /** @var list<string> */
    private array $themeNamespaces = [];

    public function __construct(
        private readonly Application $app,
        private readonly ModuleManager $modules,
    ) {
    }

    /**
     * @param  list<ThemeDescriptor>  $inheritanceChain  Parent-first, child-last.
     */
    public function apply(array $inheritanceChain): void
    {
        $this->clear();

        if ($inheritanceChain === [] || ! $this->app->bound('view')) {
            return;
        }

        foreach ($inheritanceChain as $descriptor) {
            $this->prependCoreOverrides($descriptor);
        }

        if ((bool) config('corepanel.themes.override_module_views', true)) {
            foreach ($inheritanceChain as $descriptor) {
                $this->prependModuleOverrides($descriptor);
            }
        }

        $effective = $inheritanceChain[array_key_last($inheritanceChain)];

        if ($effective->hasViews()) {
            $this->registerThemeNamespace($effective);
        }

        $this->flushViewCache();
    }

    public function clear(): void
    {
        if (! $this->app->bound('view')) {
            $this->resetTracking();

            return;
        }

        $finder = $this->viewFinder();

        if ($this->prependedCorePaths !== []) {
            $remove = $this->normalizedPaths($this->prependedCorePaths);
            $paths = array_values(array_filter(
                $finder->getPaths(),
                static fn (string $path): bool => ! in_array(
                    realpath($path) ?: $path,
                    $remove,
                    true,
                ),
            ));
            $finder->setPaths($paths);
        }

        foreach ($this->prependedModulePaths as $namespace => $paths) {
            $hints = $finder->getHints()[$namespace] ?? [];
            $remove = $this->normalizedPaths($paths);
            $hints = array_values(array_filter(
                $hints,
                static fn (string $hint): bool => ! in_array(
                    realpath($hint) ?: $hint,
                    $remove,
                    true,
                ),
            ));
            $finder->replaceNamespace($namespace, $hints);
        }

        foreach ($this->themeNamespaces as $namespace) {
            if (method_exists($finder, 'forgetNamespace')) {
                $finder->forgetNamespace($namespace);
            }
        }

        $this->resetTracking();
        $this->flushViewCache();
    }

    /**
     * @param  list<ThemeDescriptor>  $inheritanceChain
     */
    public function isApplied(array $inheritanceChain): bool
    {
        if ($inheritanceChain === []) {
            return $this->prependedCorePaths === []
                && $this->prependedModulePaths === []
                && $this->themeNamespaces === [];
        }

        $effective = $inheritanceChain[array_key_last($inheritanceChain)];

        return in_array($effective->key, $this->themeNamespaces, true);
    }

    private function prependCoreOverrides(ThemeDescriptor $descriptor): void
    {
        if (! $descriptor->hasViews()) {
            return;
        }

        $path = $descriptor->viewsPath();
        $this->viewFinder()->prependLocation($path);
        $this->prependedCorePaths[] = $path;
    }

    private function prependModuleOverrides(ThemeDescriptor $descriptor): void
    {
        $modulesRoot = $descriptor->path
            .DIRECTORY_SEPARATOR
            .'resources'
            .DIRECTORY_SEPARATOR
            .'views'
            .DIRECTORY_SEPARATOR
            .'modules';

        if (! is_dir($modulesRoot)) {
            return;
        }

        $finder = $this->viewFinder();

        foreach ($this->modules->loaded() as $manifest) {
            $moduleKey = $manifest->key;
            $overridePath = $modulesRoot.DIRECTORY_SEPARATOR.$moduleKey;

            if (! is_dir($overridePath)) {
                continue;
            }

            if (! isset($finder->getHints()[$moduleKey])) {
                continue;
            }

            $finder->prependNamespace($moduleKey, $overridePath);
            $this->prependedModulePaths[$moduleKey][] = $overridePath;
        }
    }

    private function registerThemeNamespace(ThemeDescriptor $descriptor): void
    {
        $this->app->make('view')->addNamespace($descriptor->key, $descriptor->viewsPath());
        $this->themeNamespaces[] = $descriptor->key;
    }

    private function viewFinder(): FileViewFinder
    {
        /** @var FileViewFinder $finder */
        $finder = $this->app->make('view')->getFinder();

        return $finder;
    }

    private function flushViewCache(): void
    {
        if (! $this->app->bound('view')) {
            return;
        }

        $this->viewFinder()->flush();
    }

    private function resetTracking(): void
    {
        $this->prependedCorePaths = [];
        $this->prependedModulePaths = [];
        $this->themeNamespaces = [];
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function normalizedPaths(array $paths): array
    {
        return array_values(array_map(
            static fn (string $path): string => realpath($path) ?: $path,
            $paths,
        ));
    }
}

<?php

namespace Core\Modules\Services;

use Core\Modules\Contracts\ModuleInterface;
use Core\Modules\DataTransferObjects\ModuleLoadedResources;
use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Exceptions\InvalidModuleManifestException;
use Core\Modules\Exceptions\ModuleNotFoundException;
use Illuminate\Support\Collection;
use JsonException;

class ModuleManager
{
    /** @var array<string, ModuleManifest> */
    private array $modules = [];

    /** @var array<string, ModuleManifest> */
    private array $loaded = [];

    /** @var array<string, ModuleInterface> */
    private array $instances = [];

    private bool $scanned = false;

    public function __construct(
        private readonly ModuleStateRepository $state,
        private readonly ModuleFactory $factory,
        private readonly ModuleRequirementChecker $requirements,
        private readonly ModuleSandbox $sandbox,
        private readonly ModuleServiceProviderRegistrar $providers,
        private readonly ModuleResourceLoader $resources,
        private readonly ?string $modulesPath = null,
    ) {
    }

    /**
     * Scan the modules directory and cache discovered manifests.
     *
     * @return Collection<int, ModuleManifest>
     */
    public function discover(bool $refresh = false): Collection
    {
        if ($this->scanned && ! $refresh) {
            return collect(array_values($this->modules));
        }

        $this->modules = [];
        $root = $this->path();

        if (! is_dir($root)) {
            $this->scanned = true;

            return collect();
        }

        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $directory = $root.DIRECTORY_SEPARATOR.$entry;

            if (! is_dir($directory)) {
                continue;
            }

            if (! is_file($directory.DIRECTORY_SEPARATOR.'module.json')) {
                continue;
            }

            try {
                $manifest = ModuleManifest::fromDirectory($directory, $entry);
            } catch (InvalidModuleManifestException|JsonException) {
                continue;
            }

            $this->modules[$manifest->key] = $manifest;
        }

        ksort($this->modules);
        $this->scanned = true;

        return collect(array_values($this->modules));
    }

    /**
     * Load a discovered module into the runtime registry.
     */
    public function load(string $key): ModuleManifest
    {
        $manifest = $this->findOrFail($key);

        $this->requirements->assertSatisfied($manifest);

        if (! isset($this->loaded[$manifest->key])) {
            $this->providers->register($manifest);
            $this->resources->load($manifest);

            $instance = $this->factory->make($manifest);

            if ($instance !== null) {
                $this->sandbox->run($manifest->key, function () use ($instance): void {
                    $instance->register();
                    $instance->boot();
                });

                $this->instances[$manifest->key] = $instance;
            }

            $this->loaded[$manifest->key] = $manifest;
        }

        return $manifest;
    }

    /**
     * Persist enabled state and load the module.
     */
    public function enable(string $key): ModuleManifest
    {
        $manifest = $this->load($key);

        $this->state->enable($manifest->key);

        $instance = $this->instance($manifest->key);

        if ($instance !== null) {
            $this->sandbox->run($manifest->key, fn () => $instance->enable());
        }

        return $manifest;
    }

    /**
     * Persist disabled state and unload the module.
     */
    public function disable(string $key): ModuleManifest
    {
        $manifest = $this->findOrFail($key);

        $instance = $this->instance($manifest->key);

        if ($instance !== null) {
            $this->sandbox->run($manifest->key, fn () => $instance->disable());
        }

        $this->state->disable($manifest->key);

        unset($this->loaded[$manifest->key], $this->instances[$manifest->key]);
        $this->providers->forget($manifest->key);
        $this->resources->forget($manifest->key);

        return $manifest;
    }

    /**
     * Load every currently enabled module that is still present on disk.
     *
     * @return Collection<int, ModuleManifest>
     */
    public function loadEnabled(): Collection
    {
        $this->discover();

        $loaded = collect();

        foreach ($this->state->enabledKeys() as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $loaded->push($this->load($key));
        }

        return $loaded->values();
    }

    public function has(string $key): bool
    {
        $this->discover();

        return isset($this->modules[$key]);
    }

    public function get(string $key): ?ModuleManifest
    {
        $this->discover();

        return $this->modules[$key] ?? null;
    }

    public function instance(string $key): ?ModuleInterface
    {
        return $this->instances[$key] ?? null;
    }

    public function isEnabled(string $key): bool
    {
        return $this->state->isEnabled($key);
    }

    public function isLoaded(string $key): bool
    {
        return isset($this->loaded[$key]);
    }

    /**
     * @return Collection<int, ModuleManifest>
     */
    public function all(): Collection
    {
        return $this->discover();
    }

    /**
     * @return Collection<int, ModuleManifest>
     */
    public function enabled(): Collection
    {
        $this->discover();

        return collect($this->modules)
            ->filter(fn (ModuleManifest $manifest): bool => $this->isEnabled($manifest->key))
            ->values();
    }

    /**
     * @return Collection<int, ModuleManifest>
     */
    public function loaded(): Collection
    {
        return collect(array_values($this->loaded));
    }

    /**
     * @return Collection<int, ModuleInterface>
     */
    public function instances(): Collection
    {
        return collect(array_values($this->instances));
    }

    /**
     * @return list<string>
     */
    public function registeredProviders(string $key): array
    {
        return $this->providers->providersFor($key);
    }

    public function loadedResources(string $key): ?ModuleLoadedResources
    {
        return $this->resources->resourcesFor($key);
    }

    public function path(): string
    {
        return $this->modulesPath
            ?? (string) config('corepanel.modules.path', base_path('Modules'));
    }

    /**
     * @throws ModuleNotFoundException
     */
    private function findOrFail(string $key): ModuleManifest
    {
        $this->discover();

        if (! isset($this->modules[$key])) {
            throw ModuleNotFoundException::withKey($key);
        }

        return $this->modules[$key];
    }
}

<?php

namespace Core\Themes\Services;

use Core\Themes\DataTransferObjects\ThemeDescriptor;
use Core\Themes\Exceptions\InvalidThemeManifestException;
use Core\Themes\Exceptions\ThemeNotFoundException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use JsonException;

/**
 * Discovers theme packages, loads view resources, and manages active/preview state.
 */
class ThemeManager
{
    public const PREVIEW_SESSION_KEY = 'corepanel.theme.preview';

    /** @var array<string, ThemeDescriptor> */
    private array $themes = [];

    /** @var array<string, ThemeDescriptor> */
    private array $loaded = [];

    private bool $scanned = false;

    public function __construct(
        private readonly Application $app,
        private readonly ThemeStateRepository $state,
        private readonly ?string $themesPath = null,
    ) {
    }

    /**
     * @return Collection<int, ThemeDescriptor>
     */
    public function discover(bool $refresh = false): Collection
    {
        if ($this->scanned && ! $refresh) {
            return collect(array_values($this->themes));
        }

        $this->themes = [];
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

            if (! is_file($directory.DIRECTORY_SEPARATOR.'theme.json')) {
                continue;
            }

            try {
                $descriptor = ThemeDescriptor::fromDirectory($directory, $entry);
            } catch (InvalidThemeManifestException|JsonException) {
                continue;
            }

            $this->themes[$descriptor->key] = $descriptor;
        }

        ksort($this->themes);
        $this->scanned = true;

        return collect(array_values($this->themes));
    }

    /**
     * Register a theme's runtime resources (view namespace).
     */
    public function load(string $key): ThemeDescriptor
    {
        $descriptor = $this->findOrFail($key);

        if (isset($this->loaded[$descriptor->key])) {
            return $descriptor;
        }

        if ($descriptor->hasViews() && $this->app->bound('view')) {
            $this->app->make('view')->addNamespace($descriptor->key, $descriptor->viewsPath());
        }

        $this->loaded[$descriptor->key] = $descriptor;

        return $descriptor;
    }

    /**
     * Persist and load the globally active theme.
     */
    public function activate(string $key): ThemeDescriptor
    {
        $descriptor = $this->load($key);
        $this->state->setActiveKey($descriptor->key);

        return $descriptor;
    }

    /**
     * Store a session-scoped preview theme without changing the global active theme.
     */
    public function preview(string $key, Session $session): ThemeDescriptor
    {
        $descriptor = $this->load($key);
        $session->put(self::PREVIEW_SESSION_KEY, $descriptor->key);

        return $descriptor;
    }

    public function clearPreview(Session $session): void
    {
        $session->forget(self::PREVIEW_SESSION_KEY);
    }

    public function previewKey(?Session $session = null): ?string
    {
        if ($session === null) {
            return null;
        }

        $key = $session->get(self::PREVIEW_SESSION_KEY);

        if (! is_string($key) || trim($key) === '') {
            return null;
        }

        $key = trim($key);

        return $this->has($key) ? $key : null;
    }

    public function activeKey(): ?string
    {
        $configured = $this->state->activeKey();

        if ($configured !== null && $this->has($configured)) {
            return $configured;
        }

        $default = trim((string) config('corepanel.themes.default', 'default'));

        if ($default !== '' && $this->has($default)) {
            return $default;
        }

        $first = $this->discover()->first();

        return $first?->key;
    }

    public function effectiveKey(?Session $session = null): ?string
    {
        $preview = $this->previewKey($session);

        if ($preview !== null) {
            return $preview;
        }

        return $this->activeKey();
    }

    /**
     * Load the globally active theme (used during application boot).
     */
    public function loadActive(): ?ThemeDescriptor
    {
        $key = $this->activeKey();

        if ($key === null) {
            return null;
        }

        return $this->load($key);
    }

    /**
     * Load whichever theme is effective for the current session (preview wins).
     */
    public function loadEffective(?Session $session = null): ?ThemeDescriptor
    {
        $key = $this->effectiveKey($session);

        if ($key === null) {
            return null;
        }

        return $this->load($key);
    }

    public function unload(string $key): void
    {
        unset($this->loaded[$key]);

        if (! $this->app->bound('view')) {
            return;
        }

        $finder = $this->app->make('view')->getFinder();

        if (method_exists($finder, 'forgetNamespace')) {
            $finder->forgetNamespace($key);
        }
    }

    public function has(string $key): bool
    {
        $this->discover();

        return isset($this->themes[$key]);
    }

    public function isLoaded(string $key): bool
    {
        return isset($this->loaded[$key]);
    }

    /**
     * @return Collection<int, ThemeDescriptor>
     */
    public function all(): Collection
    {
        $this->discover();

        return collect(array_values($this->themes));
    }

    public function find(string $key): ?ThemeDescriptor
    {
        $this->discover();

        return $this->themes[$key] ?? null;
    }

    public function findOrFail(string $key): ThemeDescriptor
    {
        $descriptor = $this->find($key);

        if ($descriptor === null) {
            throw ThemeNotFoundException::forKey($key);
        }

        return $descriptor;
    }

    public function path(): string
    {
        $configured = $this->themesPath ?? config('corepanel.themes.path');

        if (! is_string($configured) || $configured === '') {
            return base_path('Themes');
        }

        return $configured;
    }
}

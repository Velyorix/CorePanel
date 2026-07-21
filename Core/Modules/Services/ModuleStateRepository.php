<?php

namespace Core\Modules\Services;

use Illuminate\Support\Facades\File;

/**
 * Persists which modules are enabled until a dedicated install registry exists.
 */
class ModuleStateRepository
{
    public function __construct(
        private readonly ?string $statePath = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function enabledKeys(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return $this->defaultEnabledKeys();
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return $this->defaultEnabledKeys();
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return $this->defaultEnabledKeys();
        }

        $enabled = $decoded['enabled'] ?? [];

        if (! is_array($enabled)) {
            return $this->defaultEnabledKeys();
        }

        $keys = [];

        foreach ($enabled as $key) {
            if (is_string($key) && trim($key) !== '') {
                $keys[] = trim($key);
            }
        }

        return array_values(array_unique($keys));
    }

    public function isEnabled(string $key): bool
    {
        return in_array($key, $this->enabledKeys(), true);
    }

    public function enable(string $key): void
    {
        $enabled = $this->enabledKeys();

        if (! in_array($key, $enabled, true)) {
            $enabled[] = $key;
        }

        $this->write($enabled);
    }

    public function disable(string $key): void
    {
        $this->write(array_values(array_filter(
            $this->enabledKeys(),
            static fn (string $enabledKey): bool => $enabledKey !== $key,
        )));
    }

    /**
     * @param  list<string>  $enabled
     */
    public function write(array $enabled): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            File::ensureDirectoryExists($directory);
        }

        $payload = json_encode(
            [
                'enabled' => array_values(array_unique($enabled)),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );

        if ($payload === false) {
            throw new \RuntimeException('Unable to encode module enabled state.');
        }

        File::put($path, $payload.PHP_EOL);
    }

    public function path(): string
    {
        return $this->statePath
            ?? (string) config('corepanel.modules.state_path', storage_path('app/modules/enabled.json'));
    }

    /**
     * @return list<string>
     */
    private function defaultEnabledKeys(): array
    {
        $defaults = config('corepanel.modules.enabled', []);

        if (! is_array($defaults)) {
            return [];
        }

        $keys = [];

        foreach ($defaults as $key) {
            if (is_string($key) && trim($key) !== '') {
                $keys[] = trim($key);
            }
        }

        return array_values(array_unique($keys));
    }
}

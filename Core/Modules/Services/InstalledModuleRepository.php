<?php

namespace Core\Modules\Services;

use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Exceptions\ModuleNotFoundException;
use Core\Modules\Models\InstalledModule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class InstalledModuleRepository
{
    public function __construct(
        private readonly ModuleSignatureVerifier $signatures,
    ) {
    }

    /**
     * @return list<string>
     */
    public function enabledKeys(): array
    {
        if (! $this->ready()) {
            return $this->defaultEnabledKeys();
        }

        return InstalledModule::query()
            ->where('enabled', true)
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    public function isEnabled(string $key): bool
    {
        return in_array($key, $this->enabledKeys(), true);
    }

    public function find(string $key): ?InstalledModule
    {
        if (! $this->ready()) {
            return null;
        }

        return InstalledModule::query()->where('name', $key)->first();
    }

    /**
     * @return Collection<int, InstalledModule>
     */
    public function all(): Collection
    {
        if (! $this->ready()) {
            return new Collection;
        }

        return InstalledModule::query()->orderBy('name')->get();
    }

    /**
     * Verify package integrity and upsert the install registry row.
     */
    public function install(ModuleManifest $manifest, bool $enable = false): InstalledModule
    {
        $verification = $this->signatures->verify($manifest);

        $record = InstalledModule::query()->firstOrNew(['name' => $manifest->key]);

        $now = now();

        if (! $record->exists) {
            $record->installed_at = $now;
        }

        $record->fill([
            'version' => $manifest->version,
            'config' => $record->config,
            'checksum' => $verification['checksum'],
            'signature' => $verification['signature'],
            'path' => $manifest->path,
            'updated_at' => $now,
        ]);

        if ($enable) {
            $record->enabled = true;
            $record->enabled_at = $record->enabled_at ?? $now;
        } elseif (! $record->exists) {
            $record->enabled = false;
        }

        $record->save();

        return $record->refresh();
    }

    public function enable(ModuleManifest $manifest): InstalledModule
    {
        $record = $this->install($manifest, enable: true);

        if (! $record->enabled) {
            $record->enabled = true;
            $record->enabled_at = now();
            $record->updated_at = now();
            $record->save();
        }

        return $record->refresh();
    }

    public function disable(string $key): ?InstalledModule
    {
        $record = $this->find($key);

        if ($record === null) {
            return null;
        }

        $record->enabled = false;
        $record->enabled_at = null;
        $record->updated_at = now();
        $record->save();

        return $record->refresh();
    }

    public function uninstall(string $key): bool
    {
        $record = $this->find($key);

        if ($record === null) {
            return false;
        }

        return (bool) $record->delete();
    }

    /**
     * Re-verify an already installed module against the on-disk package.
     */
    public function assertIntegrity(ModuleManifest $manifest): void
    {
        $verification = $this->signatures->verify($manifest);
        $record = $this->find($manifest->key);

        if ($record === null) {
            return;
        }

        $record->checksum = $verification['checksum'];
        $record->signature = $verification['signature'];
        $record->version = $manifest->version;
        $record->path = $manifest->path;
        $record->updated_at = now();
        $record->save();
    }

    public function updateConfig(string $key, ?array $config): InstalledModule
    {
        $record = $this->find($key);

        if ($record === null) {
            throw ModuleNotFoundException::withKey($key);
        }

        $record->config = $config;
        $record->updated_at = now();
        $record->save();

        return $record->refresh();
    }

    public function ready(): bool
    {
        try {
            return Schema::hasTable('installed_modules');
        } catch (\Throwable) {
            return false;
        }
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

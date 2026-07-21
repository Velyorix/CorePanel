<?php

namespace Core\Modules\Support;

use Core\Modules\Contracts\ModuleInterface;
use Core\Modules\DataTransferObjects\ModuleManifest;

abstract class AbstractModule implements ModuleInterface
{
    public function __construct(
        protected readonly ModuleManifest $manifest,
    ) {
    }

    public function key(): string
    {
        return $this->manifest->key;
    }

    public function name(): string
    {
        return $this->manifest->name;
    }

    public function version(): string
    {
        return $this->manifest->version;
    }

    public function description(): ?string
    {
        return $this->manifest->description;
    }

    /**
     * @return list<string>
     */
    public function capabilities(): array
    {
        return $this->manifest->capabilities;
    }

    public function manifest(): ModuleManifest
    {
        return $this->manifest;
    }

    public function register(): void
    {
    }

    public function boot(): void
    {
    }

    public function enable(): void
    {
    }

    public function disable(): void
    {
    }
}

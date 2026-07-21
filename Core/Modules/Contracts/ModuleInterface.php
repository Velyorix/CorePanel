<?php

namespace Core\Modules\Contracts;

use Core\Modules\DataTransferObjects\ModuleManifest;

/**
 * Contract every installable module package must implement.
 *
 * Concrete modules typically extend AbstractModule and declare their class
 * in module.json via the "module" field.
 */
interface ModuleInterface
{
    /**
     * Stable machine key (matches module.json "name").
     */
    public function key(): string;

    /**
     * Human-readable display name.
     */
    public function name(): string;

    /**
     * Semver package version.
     */
    public function version(): string;

    public function description(): ?string;

    /**
     * Integration capability identifiers (e.g. server_provider).
     *
     * @return list<string>
     */
    public function capabilities(): array;

    public function manifest(): ModuleManifest;

    /**
     * Bind services and container definitions.
     */
    public function register(): void;

    /**
     * Boot after registration (routes, events, views — later expansions).
     */
    public function boot(): void;

    /**
     * Called when the module is enabled.
     */
    public function enable(): void;

    /**
     * Called when the module is disabled.
     */
    public function disable(): void;
}

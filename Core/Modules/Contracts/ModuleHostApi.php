<?php

namespace Core\Modules\Contracts;

/**
 * Mediated Core surface available to modules inside the sandbox.
 *
 * Modules must not query Core tables directly; use this API (and provider
 * contracts) instead.
 */
interface ModuleHostApi
{
    public function moduleKey(): string;

    public function coreVersion(): string;

    public function config(string $key, mixed $default = null): mixed;

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $level, string $message, array $context = []): void;

    /**
     * Register module permissions through Core (bypasses DB sandbox).
     *
     * @param  list<string|array{name?: string, description?: string|null}>  $permissions
     */
    public function registerPermissions(array $permissions = []): int;

    public function registerHook(string $hook, callable $callback, int $priority = 10): string;

    public function registerFilter(string $filter, callable $callback, int $priority = 10): string;

    public function listenEvent(string $event, callable $callback, int $priority = 10): string;
}

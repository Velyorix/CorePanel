<?php

namespace Core\Providers\Contracts;

use Core\Services\Models\Service;

/**
 * Server lifecycle contract implemented by external module providers.
 *
 * Maps to product capabilities such as server.create, server.suspend, etc.
 * The provisioning engine orchestrates these calls; Core does not
 * ship concrete implementations.
 *
 * @see \Core\Products\Enums\ProductModuleCapability
 */
interface ServerProviderInterface
{
    /**
     * Stable module key used for registry resolution (e.g. pterodactyl, proxmox).
     */
    public function key(): string;

    /**
     * Human-readable provider label for admin UI.
     */
    public function label(): string;

    /**
     * Provision a new service on the external module.
     *
     * Must be idempotent when the service already exists remotely
     * (same external_id or deterministic lookup).
     *
     * @return array{
     *     status: string,
     *     external_id?: string|null,
     *     response: array<string, mixed>
     * }
     */
    public function create(Service $service): array;

    /**
     * Suspend an active service on the external module.
     *
     * @return array{status: string, response: array<string, mixed>}
     */
    public function suspend(Service $service): array;

    /**
     * Restore a suspended service on the external module.
     *
     * @return array{status: string, response: array<string, mixed>}
     */
    public function unsuspend(Service $service): array;

    /**
     * Permanently remove or decommission the service on the external module.
     *
     * @return array{status: string, response: array<string, mixed>}
     */
    public function terminate(Service $service): array;

    /**
     * Reinstall or rebuild the service on the external module.
     *
     * @return array{status: string, response: array<string, mixed>}
     */
    public function reinstall(Service $service): array;
}

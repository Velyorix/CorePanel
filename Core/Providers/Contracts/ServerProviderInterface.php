<?php

namespace Core\Providers\Contracts;

use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;

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
     */
    public function create(ProvisioningRequest $request): ProvisioningResponse;

    /**
     * Suspend an active service on the external module.
     */
    public function suspend(ProvisioningRequest $request): ProvisioningResponse;

    /**
     * Restore a suspended service on the external module.
     */
    public function unsuspend(ProvisioningRequest $request): ProvisioningResponse;

    /**
     * Permanently remove or decommission the service on the external module.
     */
    public function terminate(ProvisioningRequest $request): ProvisioningResponse;

    /**
     * Reinstall or rebuild the service on the external module.
     */
    public function reinstall(ProvisioningRequest $request): ProvisioningResponse;

    /**
     * Fetch the current remote state for an already-provisioned service.
     *
     * Used by periodic sync jobs to poll external providers without mutating
     * local lifecycle state (comparison and reconciliation happen elsewhere).
     */
    public function getStatus(ProvisioningRequest $request): ProvisioningResponse;
}

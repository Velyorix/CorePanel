<?php

namespace Core\Providers\Contracts;

use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\NodeResourcesResponse;

/**
 * Node infrastructure contract implemented by external module providers.
 *
 * Used for connection tests (admin server setup), resource sync jobs,
 * and capacity queries during node selection.
 *
 * @see \Core\Products\Enums\ProductModuleCapability::NodeSync
 * @see \Core\Products\Enums\ProductModuleCapability::NodeAllocate
 */
interface NodeProviderInterface
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
     * Validate API credentials and remote reachability for a node configuration.
     *
     * Called from admin "test connection" before or after a node is persisted.
     */
    public function testConnection(NodeConnectionRequest $node): NodeOperationResponse;

    /**
     * Synchronize remote node state into CorePanel.
     *
     * Used by background sync jobs to reconcile services, capacity, and health.
     */
    public function sync(NodeConnectionRequest $node): NodeOperationResponse;

    /**
     * Fetch current capacity and utilization metrics for node selection.
     */
    public function getResources(NodeConnectionRequest $node): NodeResourcesResponse;
}

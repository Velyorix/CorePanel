<?php

namespace Core\Providers\Contracts;

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
     *
     * @param  array{
     *     id?: int|null,
     *     module?: string|null,
     *     name?: string|null,
     *     hostname: string,
     *     ip_address?: string|null,
     *     api_url?: string|null,
     *     credentials?: array<string, mixed>|null,
     *     config?: array<string, mixed>|null,
     * }  $node
     *
     * @return array{
     *     status: string,
     *     message?: string|null,
     *     response: array<string, mixed>
     * }
     */
    public function testConnection(array $node): array;

    /**
     * Synchronize remote node state into CorePanel.
     *
     * Used by background sync jobs to reconcile services, capacity, and health.
     *
     * @param  array{
     *     id?: int|null,
     *     module?: string|null,
     *     name?: string|null,
     *     hostname: string,
     *     ip_address?: string|null,
     *     api_url?: string|null,
     *     credentials?: array<string, mixed>|null,
     *     config?: array<string, mixed>|null,
     * }  $node
     *
     * @return array{
     *     status: string,
     *     changes?: list<array<string, mixed>>,
     *     response: array<string, mixed>
     * }
     */
    public function sync(array $node): array;

    /**
     * Fetch current capacity and utilization metrics for node selection.
     *
     * @param  array{
     *     id?: int|null,
     *     module?: string|null,
     *     name?: string|null,
     *     hostname: string,
     *     ip_address?: string|null,
     *     api_url?: string|null,
     *     credentials?: array<string, mixed>|null,
     *     config?: array<string, mixed>|null,
     *     max_services?: int|null,
     * }  $node
     *
     * @return array{
     *     status: string,
     *     resources: array{
     *         max_services?: int|null,
     *         current_services?: int|null,
     *         cpu_usage?: float|null,
     *         ram_usage?: float|null,
     *         disk_usage?: float|null,
     *         network_in?: float|null,
     *         network_out?: float|null,
     *         load_average?: float|null,
     *         capacity_available?: bool,
     *     },
     *     response: array<string, mixed>
     * }
     */
    public function getResources(array $node): array;
}

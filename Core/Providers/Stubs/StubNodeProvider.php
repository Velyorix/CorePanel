<?php

namespace Core\Providers\Stubs;

use Core\Nodes\Services\NodeSshProbeService;
use Core\Providers\Contracts\NodeProviderInterface;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\NodeResourcesData;
use Core\Providers\DataTransferObjects\NodeResourcesResponse;

/**
 * In-process node provider companion for StubServerProvider.
 *
 * When SSH credentials are configured, health checks and metrics are collected
 * over a secure SSH session. Otherwise deterministic stub values are returned.
 */
class StubNodeProvider implements NodeProviderInterface
{
    public function __construct(
        private readonly StubServerProvider $server,
        private readonly NodeSshProbeService $sshProbe,
    ) {
    }

    public function key(): string
    {
        return $this->server->key();
    }

    public function label(): string
    {
        return $this->server->label().' Nodes';
    }

    public function testConnection(NodeConnectionRequest $node): NodeOperationResponse
    {
        if ($node->hostname === '') {
            return NodeOperationResponse::failed('Hostname is required.');
        }

        if ($this->sshProbe->supports($node)) {
            return $this->sshProbe->testConnection($node);
        }

        return NodeOperationResponse::success(
            message: 'Stub connection OK for '.$node->hostname,
            payload: [
                'provider' => $this->key(),
                'hostname' => $node->hostname,
                'transport' => 'stub',
            ],
        );
    }

    public function sync(NodeConnectionRequest $node): NodeOperationResponse
    {
        if ($this->sshProbe->supports($node)) {
            return NodeOperationResponse::success(
                message: __('SSH sync completed.'),
                payload: [
                    'provider' => $this->key(),
                    'node_id' => $node->id,
                    'transport' => 'ssh',
                ],
            );
        }

        return NodeOperationResponse::success(
            message: 'Stub sync completed.',
            payload: [
                'provider' => $this->key(),
                'node_id' => $node->id,
                'transport' => 'stub',
            ],
            changes: [],
        );
    }

    public function getResources(NodeConnectionRequest $node): NodeResourcesResponse
    {
        if ($this->sshProbe->supports($node)) {
            $response = $this->sshProbe->collectResources($node);

            return new NodeResourcesResponse(
                status: $response->status,
                resources: $response->resources,
                payload: array_merge([
                    'provider' => $this->key(),
                    'node_id' => $node->id,
                ], $response->payload),
                message: $response->message,
            );
        }

        $max = $node->maxServices ?? 100;

        return NodeResourcesResponse::success(
            new NodeResourcesData(
                maxServices: $max,
                currentServices: 0,
                cpuUsage: 5.0,
                ramUsage: 8192.0,
                diskUsage: 120.0,
                networkIn: 45.5,
                networkOut: 22.0,
                loadAverage: 1.25,
                capacityAvailable: true,
            ),
            payload: [
                'provider' => $this->key(),
                'node_id' => $node->id,
                'transport' => 'stub',
            ],
        );
    }
}

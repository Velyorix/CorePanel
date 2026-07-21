<?php

namespace Core\Providers\Stubs;

use Core\Providers\Contracts\NodeProviderInterface;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\NodeResourcesData;
use Core\Providers\DataTransferObjects\NodeResourcesResponse;

/**
 * In-process node provider companion for StubServerProvider.
 */
class StubNodeProvider implements NodeProviderInterface
{
    public function __construct(
        private readonly StubServerProvider $server,
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

        return NodeOperationResponse::success(
            message: 'Stub connection OK for '.$node->hostname,
            payload: [
                'provider' => $this->key(),
                'hostname' => $node->hostname,
            ],
        );
    }

    public function sync(NodeConnectionRequest $node): NodeOperationResponse
    {
        return NodeOperationResponse::success(
            message: 'Stub sync completed.',
            payload: [
                'provider' => $this->key(),
                'node_id' => $node->id,
            ],
            changes: [],
        );
    }

    public function getResources(NodeConnectionRequest $node): NodeResourcesResponse
    {
        $max = $node->maxServices ?? 100;
        $current = 0;

        return NodeResourcesResponse::success(
            new NodeResourcesData(
                maxServices: $max,
                currentServices: $current,
                cpuUsage: 5.0,
                capacityAvailable: true,
            ),
            payload: [
                'provider' => $this->key(),
                'node_id' => $node->id,
            ],
        );
    }
}

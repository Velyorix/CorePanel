<?php

namespace Core\Nodes\Services;

use Core\Nodes\Models\Node;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\Services\ProviderRegistry;
use Core\Nodes\Exceptions\NodeSecurityException;
use InvalidArgumentException;

class NodeConnectionTestService
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly NodeConnectionSecurityService $security,
    ) {
    }

    public function testNode(Node $node): NodeOperationResponse
    {
        return $this->testConnectionRequest($node->toConnectionRequest());
    }

    /**
     * @param  array{
     *     hostname: string,
     *     module?: string|null,
     *     name?: string|null,
     *     ip_address?: string|null,
     *     api_url?: string|null,
     *     credentials?: array<string, mixed>|null,
     *     config?: array<string, mixed>|null,
     *     max_services?: int|null,
     *     id?: int|null
     * }  $payload
     */
    public function testPayload(array $payload): NodeOperationResponse
    {
        return $this->testConnectionRequest(NodeConnectionRequest::fromArray($payload));
    }

    private function testConnectionRequest(NodeConnectionRequest $request): NodeOperationResponse
    {
        $module = trim((string) ($request->module ?? ''));

        if ($module === '') {
            throw new InvalidArgumentException('A provider module is required to test the connection.');
        }

        if (! $this->providers->hasNode($module)) {
            throw new InvalidArgumentException("No node provider registered for module [{$module}].");
        }

        $this->security->assertAllowed($request);

        return $this->providers->node($module)->testConnection($request);
    }
}

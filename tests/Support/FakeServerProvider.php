<?php

namespace Tests\Support;

use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;

/**
 * Minimal server provider double for tests.
 */
class FakeServerProvider implements ServerProviderInterface
{
    public function __construct(
        private readonly string $keyValue = 'stub',
        private readonly ?ProvisioningResponse $createResponse = null,
        private readonly ?object $createCounter = null,
    ) {
    }

    public function key(): string
    {
        return $this->keyValue;
    }

    public function label(): string
    {
        return strtoupper($this->keyValue);
    }

    public function create(ProvisioningRequest $request): ProvisioningResponse
    {
        if ($this->createCounter !== null) {
            $this->createCounter->calls = ($this->createCounter->calls ?? 0) + 1;
        }

        if ($this->createResponse !== null) {
            return $this->createResponse;
        }

        return ProvisioningResponse::success(
            externalId: 'ext-'.$request->serviceId,
            nodeId: $request->nodeId,
        );
    }

    public function suspend(ProvisioningRequest $request): ProvisioningResponse
    {
        return ProvisioningResponse::success();
    }

    public function unsuspend(ProvisioningRequest $request): ProvisioningResponse
    {
        return ProvisioningResponse::success();
    }

    public function terminate(ProvisioningRequest $request): ProvisioningResponse
    {
        return ProvisioningResponse::success();
    }

    public function reinstall(ProvisioningRequest $request): ProvisioningResponse
    {
        return ProvisioningResponse::success();
    }

    public function getStatus(ProvisioningRequest $request): ProvisioningResponse
    {
        return ProvisioningResponse::success(externalId: $request->externalId);
    }
}

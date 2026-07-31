<?php

namespace Core\Providers\Stubs;

use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;

/**
 * In-process server provider for local development and automated tests.
 *
 * Generates deterministic external IDs without calling a remote API.
 */
class StubServerProvider implements ServerProviderInterface
{
    public const KEY = 'stub';

    public function key(): string
    {
        $key = config('corepanel.provisioning.stub.key', self::KEY);

        return is_string($key) && $key !== '' ? $key : self::KEY;
    }

    public function label(): string
    {
        $label = config('corepanel.provisioning.stub.label');

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return 'Stub Provider';
    }

    public function create(ProvisioningRequest $request): ProvisioningResponse
    {
        if ($this->shouldFail('create')) {
            return ProvisioningResponse::failed('Stub provider forced create failure.');
        }

        $externalId = filled($request->externalId)
            ? (string) $request->externalId
            : $this->externalIdFor($request->serviceId);

        return ProvisioningResponse::success(
            externalId: $externalId,
            hostname: $request->hostname ?: $this->hostnameFor($request->serviceId),
            ipAddress: $request->ipAddress ?: $this->ipAddressFor($request->serviceId),
            nodeId: $request->nodeId,
            message: 'Provisioned by stub provider.',
            payload: [
                'provider' => $this->key(),
                'service_id' => $request->serviceId,
            ],
        );
    }

    public function suspend(ProvisioningRequest $request): ProvisioningResponse
    {
        if ($this->shouldFail('suspend')) {
            return ProvisioningResponse::failed('Stub provider forced suspend failure.');
        }

        return ProvisioningResponse::success(
            externalId: $request->externalId,
            message: 'Suspended by stub provider.',
        );
    }

    public function unsuspend(ProvisioningRequest $request): ProvisioningResponse
    {
        if ($this->shouldFail('unsuspend')) {
            return ProvisioningResponse::failed('Stub provider forced unsuspend failure.');
        }

        return ProvisioningResponse::success(
            externalId: $request->externalId,
            message: 'Unsuspended by stub provider.',
        );
    }

    public function terminate(ProvisioningRequest $request): ProvisioningResponse
    {
        if ($this->shouldFail('terminate')) {
            return ProvisioningResponse::failed('Stub provider forced terminate failure.');
        }

        return ProvisioningResponse::success(
            externalId: $request->externalId,
            message: 'Terminated by stub provider.',
        );
    }

    public function reinstall(ProvisioningRequest $request): ProvisioningResponse
    {
        if ($this->shouldFail('reinstall')) {
            return ProvisioningResponse::failed('Stub provider forced reinstall failure.');
        }

        return ProvisioningResponse::success(
            externalId: $request->externalId ?: $this->externalIdFor($request->serviceId),
            hostname: $request->hostname ?: $this->hostnameFor($request->serviceId),
            ipAddress: $request->ipAddress ?: $this->ipAddressFor($request->serviceId),
            nodeId: $request->nodeId,
            message: 'Reinstalled by stub provider.',
        );
    }

    public function getStatus(ProvisioningRequest $request): ProvisioningResponse
    {
        if ($this->shouldFail('getStatus')) {
            return ProvisioningResponse::failed('Stub provider forced getStatus failure.');
        }

        $externalId = filled($request->externalId)
            ? (string) $request->externalId
            : $this->externalIdFor($request->serviceId);

        return ProvisioningResponse::success(
            externalId: $externalId,
            hostname: $request->hostname ?: $this->hostnameFor($request->serviceId),
            ipAddress: $request->ipAddress ?: $this->ipAddressFor($request->serviceId),
            nodeId: $request->nodeId,
            message: 'Remote status fetched from stub provider.',
            payload: [
                'provider' => $this->key(),
                'service_id' => $request->serviceId,
                'remote_status' => $request->status->value,
                'sync_source' => 'poll',
            ],
        );
    }

    public function externalIdFor(int $serviceId): string
    {
        $prefix = (string) config('corepanel.provisioning.stub.external_id_prefix', 'stub');

        return $prefix.'-'.$serviceId;
    }

    private function hostnameFor(int $serviceId): string
    {
        $suffix = (string) config('corepanel.provisioning.stub.hostname_suffix', '.stub.local');

        return 'service-'.$serviceId.$suffix;
    }

    private function ipAddressFor(int $serviceId): string
    {
        $prefix = (string) config('corepanel.provisioning.stub.ip_prefix', '10.255.0.');
        $octet = ($serviceId % 254) + 1;

        return $prefix.$octet;
    }

    private function shouldFail(string $operation): bool
    {
        $failures = config('corepanel.provisioning.stub.fail_operations', []);

        if (! is_array($failures)) {
            return false;
        }

        return in_array($operation, $failures, true) || in_array('*', $failures, true);
    }
}

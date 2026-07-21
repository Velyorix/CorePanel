<?php

namespace Core\Sync\Services;

use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Sync\DataTransferObjects\ServiceExternalState;
use Core\Sync\DataTransferObjects\ServiceSyncComparisonResult;
use Core\Sync\DataTransferObjects\ServiceSyncDivergence;
use Core\Sync\Enums\ServiceSyncDivergenceType;

class ServiceSyncComparisonService
{
    public function compare(Service $service, ProvisioningResponse $response): ServiceSyncComparisonResult
    {
        $external = ServiceExternalState::fromProvisioningResponse($response);

        if (! $external->isComparable()) {
            return new ServiceSyncComparisonResult($external);
        }

        if ($external->exists === false) {
            return new ServiceSyncComparisonResult($external, [
                new ServiceSyncDivergence(
                    type: ServiceSyncDivergenceType::ExternalDeleted,
                    local: $service->status->value,
                    remote: 'missing',
                    message: $external->message,
                ),
            ]);
        }

        $divergences = [];

        if ($this->shouldCompare('status') && $external->status instanceof ServiceStatus) {
            $divergence = $this->compareStatus($service->status, $external->status);

            if ($divergence !== null) {
                $divergences[] = $divergence;
            }
        }

        if ($this->shouldCompare('ip_address')) {
            $divergence = $this->compareScalar(
                type: ServiceSyncDivergenceType::IpChanged,
                local: $service->ip_address,
                remote: $external->ipAddress,
            );

            if ($divergence !== null) {
                $divergences[] = $divergence;
            }
        }

        if ($this->shouldCompare('hostname')) {
            $divergence = $this->compareScalar(
                type: ServiceSyncDivergenceType::HostnameChanged,
                local: $service->hostname,
                remote: $external->hostname,
            );

            if ($divergence !== null) {
                $divergences[] = $divergence;
            }
        }

        if ($this->shouldCompare('external_id')) {
            $divergence = $this->compareScalar(
                type: ServiceSyncDivergenceType::ExternalIdChanged,
                local: $service->external_id,
                remote: $external->externalId,
            );

            if ($divergence !== null) {
                $divergences[] = $divergence;
            }
        }

        return new ServiceSyncComparisonResult($external, $divergences);
    }

    private function compareStatus(ServiceStatus $local, ServiceStatus $remote): ?ServiceSyncDivergence
    {
        if ($local === $remote) {
            return null;
        }

        return new ServiceSyncDivergence(
            type: ServiceSyncDivergenceType::StatusMismatch,
            local: $local->value,
            remote: $remote->value,
        );
    }

    private function compareScalar(
        ServiceSyncDivergenceType $type,
        ?string $local,
        ?string $remote,
    ): ?ServiceSyncDivergence {
        $local = $this->normalizeScalar($local);
        $remote = $this->normalizeScalar($remote);

        if ($local === null || $remote === null || $local === $remote) {
            return null;
        }

        return new ServiceSyncDivergence(
            type: $type,
            local: $local,
            remote: $remote,
        );
    }

    private function normalizeScalar(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function shouldCompare(string $field): bool
    {
        return (bool) config("corepanel.services.sync.compare.{$field}", true);
    }
}

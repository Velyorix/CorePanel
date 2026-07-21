<?php

namespace Core\Sync\Services;

use Core\Provisioning\Services\ProviderResourceMappingService;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Services\Services\ServiceLifecycleService;
use Core\Sync\DataTransferObjects\ServiceSyncComparisonResult;
use Core\Sync\DataTransferObjects\ServiceSyncDivergence;
use Core\Sync\DataTransferObjects\ServiceSyncResolutionResult;
use Core\Sync\Enums\ServiceSyncDivergenceType;
use Core\Sync\Enums\ServiceSyncResolutionAction;
use Illuminate\Support\Facades\Log;
use Throwable;

class ServiceSyncResolutionService
{
    public function __construct(
        private readonly ServiceLifecycleService $lifecycle,
        private readonly ProviderResourceMappingService $mappings,
    ) {
    }

    public function resolve(Service $service, ServiceSyncComparisonResult $comparison): ServiceSyncResolutionResult
    {
        if (! (bool) config('corepanel.services.sync.resolve.enabled', true)) {
            return new ServiceSyncResolutionResult(unresolved: $comparison->divergences);
        }

        if (! $comparison->hasDivergences()) {
            return new ServiceSyncResolutionResult;
        }

        $service = $service->fresh() ?? $service;
        $applied = [];
        $pending = $comparison->divergences;

        foreach ($pending as $index => $divergence) {
            if ($divergence->type !== ServiceSyncDivergenceType::ExternalDeleted) {
                continue;
            }

            if (! $this->shouldResolve('external_deleted')) {
                continue;
            }

            if ($this->terminateMissingExternal($service)) {
                $applied[] = ServiceSyncResolutionResult::appliedEntry(
                    ServiceSyncResolutionAction::TerminatedLocally,
                    [
                        'previous_status' => $divergence->local,
                        'message' => $divergence->message,
                    ],
                );

                Log::info('Service sync terminated local service after external deletion.', [
                    'service_id' => $service->id,
                    'previous_status' => $divergence->local,
                ]);

                return new ServiceSyncResolutionResult(
                    applied: $applied,
                    unresolved: [],
                );
            }

            return new ServiceSyncResolutionResult(
                applied: [],
                unresolved: [$divergence],
            );
        }

        $pending = array_values($pending);

        foreach ($pending as $index => $divergence) {
            $entry = $this->resolveDivergence($service, $divergence, $comparison);

            if ($entry !== null) {
                $applied[] = $entry;
                unset($pending[$index]);
                $service = $service->fresh() ?? $service;
            }
        }

        return new ServiceSyncResolutionResult(
            applied: $applied,
            unresolved: array_values($pending),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDivergence(
        Service $service,
        ServiceSyncDivergence $divergence,
        ServiceSyncComparisonResult $comparison,
    ): ?array {
        return match ($divergence->type) {
            ServiceSyncDivergenceType::StatusMismatch => $this->resolveStatusMismatch(
                $service,
                $comparison->external->status,
                $divergence,
            ),
            ServiceSyncDivergenceType::IpChanged => $this->resolveScalarField(
                $service,
                'ip_address',
                $comparison->external->ipAddress,
                ServiceSyncResolutionAction::IpSynced,
                'ip_address',
            ),
            ServiceSyncDivergenceType::HostnameChanged => $this->resolveScalarField(
                $service,
                'hostname',
                $comparison->external->hostname,
                ServiceSyncResolutionAction::HostnameSynced,
                'hostname',
            ),
            ServiceSyncDivergenceType::ExternalIdChanged => $this->resolveExternalId(
                $service,
                $comparison->external->externalId,
            ),
            ServiceSyncDivergenceType::ExternalDeleted => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveStatusMismatch(
        Service $service,
        ?ServiceStatus $remoteStatus,
        ServiceSyncDivergence $divergence,
    ): ?array {
        if (! $this->shouldResolve('status_mismatch') || ! $remoteStatus instanceof ServiceStatus) {
            return null;
        }

        if ($service->status === $remoteStatus) {
            return ServiceSyncResolutionResult::appliedEntry(
                ServiceSyncResolutionAction::StatusSynced,
                [
                    'from' => $divergence->local,
                    'to' => $remoteStatus->value,
                ],
            );
        }

        if (! $this->applyRemoteStatus($service, $remoteStatus)) {
            return null;
        }

        Log::info('Service sync aligned local status with remote state.', [
            'service_id' => $service->id,
            'from' => $divergence->local,
            'to' => $remoteStatus->value,
        ]);

        return ServiceSyncResolutionResult::appliedEntry(
            ServiceSyncResolutionAction::StatusSynced,
            [
                'from' => $divergence->local,
                'to' => $remoteStatus->value,
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveScalarField(
        Service $service,
        string $field,
        ?string $remoteValue,
        ServiceSyncResolutionAction $action,
        string $configKey,
    ): ?array {
        if (! $this->shouldResolve($configKey)) {
            return null;
        }

        $remoteValue = $this->normalizeScalar($remoteValue);

        if ($remoteValue === null) {
            return null;
        }

        $localValue = $this->normalizeScalar($service->{$field});

        if ($localValue === $remoteValue) {
            return ServiceSyncResolutionResult::appliedEntry($action, [
                'field' => $field,
                'value' => $remoteValue,
            ]);
        }

        $service->forceFill([$field => $remoteValue])->save();

        Log::info('Service sync updated local field from remote state.', [
            'service_id' => $service->id,
            'field' => $field,
            'from' => $localValue,
            'to' => $remoteValue,
        ]);

        return ServiceSyncResolutionResult::appliedEntry($action, [
            'field' => $field,
            'from' => $localValue,
            'to' => $remoteValue,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveExternalId(Service $service, ?string $remoteExternalId): ?array
    {
        if (! $this->shouldResolve('external_id')) {
            return null;
        }

        $remoteExternalId = $this->normalizeScalar($remoteExternalId);

        if ($remoteExternalId === null) {
            return null;
        }

        $localExternalId = $this->normalizeScalar($service->external_id);

        if ($localExternalId === $remoteExternalId) {
            return ServiceSyncResolutionResult::appliedEntry(
                ServiceSyncResolutionAction::ExternalIdSynced,
                ['to' => $remoteExternalId],
            );
        }

        $mapping = $this->mappings->findForService($service);
        $metadata = is_array($mapping?->metadata) ? $mapping->metadata : [];

        $this->mappings->upsert(
            service: $service,
            externalId: $remoteExternalId,
            module: $mapping?->module,
            metadata: $metadata,
        );

        Log::info('Service sync updated external resource mapping.', [
            'service_id' => $service->id,
            'from' => $localExternalId,
            'to' => $remoteExternalId,
        ]);

        return ServiceSyncResolutionResult::appliedEntry(
            ServiceSyncResolutionAction::ExternalIdSynced,
            [
                'from' => $localExternalId,
                'to' => $remoteExternalId,
            ],
        );
    }

    private function terminateMissingExternal(Service $service): bool
    {
        if ($service->status === ServiceStatus::Terminated) {
            return true;
        }

        if (! $service->status->canTransitionTo(ServiceStatus::Terminated)) {
            return false;
        }

        try {
            $this->lifecycle->terminate($service);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Service sync failed to terminate service after external deletion.', [
                'service_id' => $service->id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function applyRemoteStatus(Service $service, ServiceStatus $remoteStatus): bool
    {
        if ($service->status === $remoteStatus) {
            return true;
        }

        try {
            return match ($remoteStatus) {
                ServiceStatus::Active => $this->applyActive($service),
                ServiceStatus::Suspended => $this->applySuspended($service),
                ServiceStatus::Terminated => $this->applyTerminated($service),
                default => false,
            };
        } catch (Throwable $exception) {
            Log::warning('Service sync failed to align local status.', [
                'service_id' => $service->id,
                'target_status' => $remoteStatus->value,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function applyActive(Service $service): bool
    {
        if ($service->status === ServiceStatus::Active) {
            return true;
        }

        if ($service->status !== ServiceStatus::Suspended) {
            return false;
        }

        $this->lifecycle->unsuspend($service);

        return true;
    }

    private function applySuspended(Service $service): bool
    {
        if ($service->status === ServiceStatus::Suspended) {
            return true;
        }

        if ($service->status !== ServiceStatus::Active) {
            return false;
        }

        $this->lifecycle->suspend($service);

        return true;
    }

    private function applyTerminated(Service $service): bool
    {
        if ($service->status === ServiceStatus::Terminated) {
            return true;
        }

        if (! $service->status->canTransitionTo(ServiceStatus::Terminated)) {
            return false;
        }

        $this->lifecycle->terminate($service);

        return true;
    }

    private function shouldResolve(string $key): bool
    {
        return (bool) config("corepanel.services.sync.resolve.{$key}", true);
    }

    private function normalizeScalar(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}

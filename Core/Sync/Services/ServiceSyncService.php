<?php

namespace Core\Sync\Services;

use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Services\ProviderRegistry;
use Core\Provisioning\Services\ProviderResourceMappingService;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Sync\DataTransferObjects\ServiceSyncComparisonResult;
use Core\Sync\DataTransferObjects\ServiceSyncResolutionResult;
use Core\Sync\DataTransferObjects\ServiceSyncResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ServiceSyncService
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly ProviderResourceMappingService $mappings,
        private readonly ServiceSyncComparisonService $comparison,
        private readonly ServiceSyncResolutionService $resolution,
    ) {
    }

    public function pollAll(): ServiceSyncResult
    {
        if (! (bool) config('corepanel.services.sync.enabled', true)) {
            return new ServiceSyncResult;
        }

        $polled = 0;
        $resolved = 0;
        $diverged = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($this->syncableServices() as $service) {
            $outcome = $this->pollService($service);

            match ($outcome) {
                'polled' => $polled++,
                'resolved' => $resolved++,
                'diverged' => $diverged++,
                'failed' => $failed++,
                default => $skipped++,
            };
        }

        return new ServiceSyncResult(
            polled: $polled,
            resolved: $resolved,
            diverged: $diverged,
            failed: $failed,
            skipped: $skipped,
        );
    }

    /**
     * @return 'polled'|'resolved'|'diverged'|'failed'|'skipped'
     */
    public function pollService(Service $service): string
    {
        $module = trim((string) ($service->module ?? ''));

        if ($module === '') {
            return 'skipped';
        }

        if (! filled($service->external_id)) {
            return 'skipped';
        }

        if (! in_array($service->status, [ServiceStatus::Active, ServiceStatus::Suspended], true)) {
            return 'skipped';
        }

        if (! $this->providers->hasServer($module)) {
            Log::warning('Service sync skipped unknown provider module.', [
                'service_id' => $service->id,
                'module' => $module,
            ]);

            return 'skipped';
        }

        $service->loadMissing('node');

        try {
            $request = ProvisioningRequest::fromService(
                $service,
                $service->node?->toConnectionRequest(),
            );
        } catch (\InvalidArgumentException $exception) {
            Log::warning('Service sync skipped invalid provisioning request.', [
                'service_id' => $service->id,
                'module' => $module,
                'message' => $exception->getMessage(),
            ]);

            return 'skipped';
        }

        $response = $this->providers->server($module)->getStatus($request);
        $comparison = $this->comparison->compare($service, $response);

        if ($comparison->hasDivergences()) {
            $resolution = $this->resolution->resolve($service, $comparison);
            $service = $service->fresh() ?? $service;

            $this->recordComparison($service, $response, $comparison, $resolution);

            if ($resolution->isFullyResolved()) {
                Log::info('Service sync resolved state divergence.', [
                    'service_id' => $service->id,
                    'module' => $module,
                    'resolutions' => $resolution->appliedArrays(),
                ]);

                return 'resolved';
            }

            if ($resolution->hasApplied()) {
                Log::notice('Service sync partially resolved state divergence.', [
                    'service_id' => $service->id,
                    'module' => $module,
                    'resolutions' => $resolution->appliedArrays(),
                    'unresolved' => $resolution->unresolvedArrays(),
                ]);
            } else {
                Log::notice('Service sync detected state divergence.', [
                    'service_id' => $service->id,
                    'module' => $module,
                    'divergences' => $comparison->divergenceArrays(),
                ]);
            }

            return 'diverged';
        }

        if (! $response->status->isSuccessful()) {
            Log::warning('Service sync poll failed.', [
                'service_id' => $service->id,
                'module' => $module,
                'message' => $response->message,
                'payload' => $response->payload === [] ? null : $response->payload,
            ]);

            return 'failed';
        }

        $this->recordComparison($service, $response, $comparison);

        return 'polled';
    }

    /**
     * @return Collection<int, Service>
     */
    private function syncableServices(): Collection
    {
        return Service::query()
            ->whereNotNull('module')
            ->where('module', '!=', '')
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->whereIn('status', [
                ServiceStatus::Active->value,
                ServiceStatus::Suspended->value,
            ])
            ->orderBy('id')
            ->get();
    }

    private function recordComparison(
        Service $service,
        ProvisioningResponse $response,
        ServiceSyncComparisonResult $comparison,
        ?ServiceSyncResolutionResult $resolution = null,
    ): void {
        $mapping = $this->mappings->findForService($service);

        if ($mapping === null) {
            return;
        }

        $inSync = $resolution !== null
            ? $resolution->isFullyResolved()
            : ! $comparison->hasDivergences();

        $this->mappings->upsert(
            service: $service,
            externalId: $mapping->external_id,
            module: $mapping->module,
            metadata: array_filter([
                ...(is_array($mapping->metadata) ? $mapping->metadata : []),
                'last_poll_at' => now()->toIso8601String(),
                'last_poll_payload' => $response->payload === [] ? null : $response->payload,
                'last_comparison_at' => now()->toIso8601String(),
                'in_sync' => $inSync,
                'last_divergences' => $comparison->hasDivergences()
                    ? $comparison->divergenceArrays()
                    : null,
                'last_resolution_at' => $resolution?->hasApplied()
                    ? now()->toIso8601String()
                    : null,
                'last_resolutions' => $resolution?->hasApplied()
                    ? $resolution->appliedArrays()
                    : null,
                'last_unresolved_divergences' => $resolution !== null && $resolution->unresolved !== []
                    ? $resolution->unresolvedArrays()
                    : null,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }
}

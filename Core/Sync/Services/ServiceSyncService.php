<?php

namespace Core\Sync\Services;

use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\Services\ProviderRegistry;
use Core\Provisioning\Services\ProviderResourceMappingService;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Sync\DataTransferObjects\ServiceSyncResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ServiceSyncService
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly ProviderResourceMappingService $mappings,
    ) {
    }

    public function pollAll(): ServiceSyncResult
    {
        if (! (bool) config('corepanel.services.sync.enabled', true)) {
            return new ServiceSyncResult;
        }

        $polled = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($this->syncableServices() as $service) {
            $outcome = $this->pollService($service);

            match ($outcome) {
                'polled' => $polled++,
                'failed' => $failed++,
                default => $skipped++,
            };
        }

        return new ServiceSyncResult(
            polled: $polled,
            failed: $failed,
            skipped: $skipped,
        );
    }

    /**
     * @return 'polled'|'failed'|'skipped'
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

        if (! $response->status->isSuccessful()) {
            Log::warning('Service sync poll failed.', [
                'service_id' => $service->id,
                'module' => $module,
                'message' => $response->message,
                'payload' => $response->payload === [] ? null : $response->payload,
            ]);

            return 'failed';
        }

        $this->recordSuccessfulPoll($service, $response->payload);

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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordSuccessfulPoll(Service $service, array $payload): void
    {
        $mapping = $this->mappings->findForService($service);

        if ($mapping === null) {
            return;
        }

        $this->mappings->upsert(
            service: $service,
            externalId: $mapping->external_id,
            module: $mapping->module,
            metadata: array_filter([
                ...(is_array($mapping->metadata) ? $mapping->metadata : []),
                'last_poll_at' => now()->toIso8601String(),
                'last_poll_payload' => $payload === [] ? null : $payload,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }
}

<?php

namespace Core\Provisioning\Services;

use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Enums\ProviderOperationStatus;
use Core\Providers\Exceptions\UnknownProviderException;
use Core\Providers\Services\ProviderRegistry;
use Core\Provisioning\Exceptions\ProvisioningAttemptFailedException;
use Core\Provisioning\Exceptions\ProvisioningException;
use Core\Provisioning\Jobs\ProvisionServiceJob;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Services\Services\ServiceLifecycleService;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates service provisioning against registered server providers.
 *
 * Flow: resolve module → mark provisioning → provider.create()
 * → persist mapping + provider fields → activate or fail.
 *
 * When $retryableFailures is true (queue jobs), provider Failed responses throw
 * so the worker can retry with backoff; permanent Failed is applied in job::failed().
 */
class ProvisioningEngine
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly ServiceLifecycleService $lifecycle,
        private readonly ProviderResourceMappingService $mappings,
    ) {
    }

    /**
     * Whether the service should be auto-queued after creation.
     */
    public function shouldAutoQueue(Service $service): bool
    {
        $service->loadMissing('product.provisioningRules');

        $module = trim((string) ($service->module ?? ''));

        if ($module === '') {
            return false;
        }

        if (! $service->product?->shouldAutoProvision()) {
            return false;
        }

        return $this->providers->hasServer($module);
    }

    /**
     * Dispatch asynchronous provisioning for a service.
     */
    public function queue(Service $service): void
    {
        ProvisionServiceJob::dispatch((int) $service->id);
    }

    /**
     * Queue provisioning when the product is auto-provisionable and a provider exists.
     */
    public function queueIfEligible(Service $service): bool
    {
        if (! $this->shouldAutoQueue($service)) {
            return false;
        }

        $this->queue($service);

        return true;
    }

    /**
     * Synchronously provision a service via its server provider.
     *
     * Idempotent when the service is already active with an external mapping / id.
     *
     * @param  bool  $retryableFailures  When true, provider Failed throws instead of marking Failed.
     */
    public function provision(Service $service, bool $retryableFailures = false): ProvisioningResponse
    {
        $service = $service->fresh(['product', 'client']) ?? $service;

        if ($this->alreadyProvisioned($service)) {
            return ProvisioningResponse::skipped('Service already provisioned.');
        }

        $existingMapping = $this->mappings->findForService($service);

        if ($existingMapping !== null) {
            return $this->activateFromExistingMapping($service, $existingMapping->external_id);
        }

        $module = trim((string) ($service->module ?? ''));

        if ($module === '') {
            throw ProvisioningException::missingModule($service);
        }

        if (! $this->providers->hasServer($module)) {
            throw UnknownProviderException::forServer($module);
        }

        if (in_array($service->status, [ServiceStatus::Pending, ServiceStatus::Failed], true)) {
            $service = $this->lifecycle->markProvisioning($service);
        }

        if ($service->status !== ServiceStatus::Provisioning) {
            throw ProvisioningException::invalidStatus($service);
        }

        $provider = $this->providers->server($module);
        $request = ProvisioningRequest::fromService($service);
        $response = $provider->create($request);

        return $this->applyResponse($service, $response, $retryableFailures);
    }

    private function alreadyProvisioned(Service $service): bool
    {
        if ($service->status !== ServiceStatus::Active) {
            return false;
        }

        return filled($service->external_id) || $this->mappings->hasMapping($service);
    }

    private function activateFromExistingMapping(Service $service, string $externalId): ProvisioningResponse
    {
        DB::transaction(function () use ($service, $externalId): void {
            if ($service->external_id !== $externalId) {
                $service->forceFill(['external_id' => $externalId])->save();
            }

            if ($service->status === ServiceStatus::Provisioning
                || $service->status === ServiceStatus::Pending
                || $service->status === ServiceStatus::Failed) {
                if ($service->status !== ServiceStatus::Provisioning) {
                    $service = $this->lifecycle->markProvisioning($service);
                }

                $this->lifecycle->markActive($service);
            }
        });

        return ProvisioningResponse::skipped(
            message: 'Service already mapped to provider resource.',
            payload: ['external_id' => $externalId],
            externalId: $externalId,
        );
    }

    private function applyResponse(
        Service $service,
        ProvisioningResponse $response,
        bool $retryableFailures,
    ): ProvisioningResponse {
        return match ($response->status) {
            ProviderOperationStatus::Success => $this->applySuccess($service, $response),
            ProviderOperationStatus::Failed => $this->applyFailure($service, $response, $retryableFailures),
            ProviderOperationStatus::Pending,
            ProviderOperationStatus::Skipped => $response,
        };
    }

    private function applySuccess(Service $service, ProvisioningResponse $response): ProvisioningResponse
    {
        DB::transaction(function () use ($service, $response): void {
            $this->mappings->syncFromProvisioningResponse($service, $response);

            $service = $service->fresh() ?? $service;

            $service->forceFill(array_filter([
                'external_id' => $response->externalId ?? $service->external_id,
                'hostname' => $response->hostname,
                'ip_address' => $response->ipAddress,
                'node_id' => $response->nodeId,
            ], static fn (mixed $value): bool => $value !== null))->save();

            $this->lifecycle->markActive($service->fresh() ?? $service);
        });

        return $response;
    }

    private function applyFailure(
        Service $service,
        ProvisioningResponse $response,
        bool $retryableFailures,
    ): ProvisioningResponse {
        if ($retryableFailures) {
            throw ProvisioningAttemptFailedException::fromMessage($response->message);
        }

        $this->lifecycle->markFailed($service);

        return $response;
    }
}

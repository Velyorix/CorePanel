<?php

namespace Core\Provisioning\Services;

use Core\Nodes\Models\Node;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Enums\ProviderOperationStatus;
use Core\Providers\Exceptions\UnknownProviderException;
use Core\Providers\Services\ProviderRegistry;
use Core\Provisioning\Events\ServiceProvisioned;
use Core\Provisioning\Events\ServiceProvisioningFailed;
use Core\Provisioning\Exceptions\NoEligibleNodeException;
use Core\Provisioning\Exceptions\NodeProvisioningDeferredException;
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
 * Flow: resolve module → select/assign node → mark provisioning → provider.create()
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
        private readonly NodeSelectionService $nodeSelection,
        private readonly ProvisioningRollbackService $rollback,
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

        try {
            $selection = $this->nodeSelection->assignToService($service);
        } catch (NodeProvisioningDeferredException $exception) {
            throw $exception;
        } catch (NoEligibleNodeException $exception) {
            if ($retryableFailures) {
                throw $exception;
            }

            $failed = $this->lifecycle->markFailed($service);

            event(new ServiceProvisioningFailed(
                $failed,
                $exception->getMessage(),
            ));

            throw $exception;
        }

        $service = $service->fresh(['product', 'client']) ?? $service;

        $provider = $this->providers->server($module);
        $request = ProvisioningRequest::fromService($service, $selection->connection);
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
        $activated = false;

        DB::transaction(function () use ($service, $externalId, &$activated): void {
            if ($service->external_id !== $externalId) {
                $service->forceFill(['external_id' => $externalId])->save();
            }

            if (in_array($service->status, [
                ServiceStatus::Provisioning,
                ServiceStatus::Pending,
                ServiceStatus::Failed,
            ], true)) {
                if ($service->status !== ServiceStatus::Provisioning) {
                    $service = $this->lifecycle->markProvisioning($service);
                }

                $this->lifecycle->markActive($service);
                $activated = true;
            }
        });

        if ($activated) {
            event(new ServiceProvisioned(
                $service->fresh() ?? $service,
                $externalId,
            ));
        }

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
                'node_id' => $this->resolvePersistedNodeId($response->nodeId, $service->node_id),
            ], static fn (mixed $value): bool => $value !== null))->save();

            $this->lifecycle->markActive($service->fresh() ?? $service);
        });

        $fresh = $service->fresh() ?? $service;

        event(new ServiceProvisioned(
            $fresh,
            $fresh->external_id ?? $response->externalId,
        ));

        return $response;
    }

    private function resolvePersistedNodeId(?int $fromResponse, mixed $current): ?int
    {
        $currentId = $current !== null ? (int) $current : null;

        if ($fromResponse === null) {
            return $currentId;
        }

        if (Node::query()->whereKey($fromResponse)->exists()) {
            return $fromResponse;
        }

        return $currentId;
    }

    private function applyFailure(
        Service $service,
        ProvisioningResponse $response,
        bool $retryableFailures,
    ): ProvisioningResponse {
        if ($retryableFailures) {
            throw ProvisioningAttemptFailedException::fromMessage($response->message);
        }

        $failed = $this->lifecycle->markFailed($service);

        if ((bool) config('corepanel.provisioning.rollback_on_failure', true)) {
            $this->rollback->rollbackLocalState($failed, [
                'clear_mapping' => true,
                'clear_external_id' => true,
                'clear_node' => false,
            ]);
        }

        event(new ServiceProvisioningFailed(
            $failed->fresh() ?? $failed,
            $response->message,
        ));

        return $response;
    }
}

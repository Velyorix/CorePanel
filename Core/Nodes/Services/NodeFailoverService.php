<?php

namespace Core\Nodes\Services;

use Core\Nodes\DataTransferObjects\NodeFailoverBatchResult;
use Core\Nodes\DataTransferObjects\NodeFailoverResult;
use Core\Nodes\Enums\NodeLogStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Jobs\ProcessNodeFailoverJob;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\Services\ProviderRegistry;
use Core\Provisioning\DataTransferObjects\NodeSelectionResult;
use Core\Provisioning\Exceptions\NoEligibleNodeException;
use Core\Provisioning\Services\NodeSelectionService;
use Core\Provisioning\Services\ProviderResourceMappingService;
use Core\Services\Contracts\ServiceActionLogger;
use Core\Services\Enums\ServiceAction;
use Core\Services\Enums\ServiceActionLogStatus;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Support\Facades\DB;

class NodeFailoverService
{
    public function __construct(
        private readonly NodeSelectionService $nodeSelection,
        private readonly ProviderRegistry $providers,
        private readonly ProviderResourceMappingService $mappings,
        private readonly ServiceActionLogger $serviceLogger,
        private readonly NodeLogService $nodeLogs,
    ) {
    }

    public function processNode(Node $failedNode): NodeFailoverBatchResult
    {
        if (! (bool) config('corepanel.nodes.failover.enabled', true)) {
            return new NodeFailoverBatchResult;
        }

        $reassigned = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($this->failoverCandidates($failedNode) as $service) {
            $result = $this->reassignService($service, $failedNode);

            if ($result->skipped) {
                $skipped++;
            } elseif ($result->reassigned) {
                $reassigned++;
            } else {
                $failed++;
            }
        }

        if ($reassigned > 0) {
            $this->nodeLogs->record(
                node: $failedNode,
                action: 'node.failover.completed',
                status: NodeLogStatus::Success,
                response: [
                    'reassigned' => $reassigned,
                    'failed' => $failed,
                    'skipped' => $skipped,
                ],
            );
        }

        return new NodeFailoverBatchResult(
            reassigned: $reassigned,
            failed: $failed,
            skipped: $skipped,
        );
    }

    public function reassignService(Service $service, Node $failedFrom): NodeFailoverResult
    {
        if (! (bool) config('corepanel.nodes.failover.enabled', true)) {
            return new NodeFailoverResult(
                serviceId: (int) $service->id,
                fromNodeId: (int) $failedFrom->id,
                skipped: true,
                message: 'failover_disabled',
            );
        }

        if ((int) $service->node_id !== (int) $failedFrom->id) {
            return new NodeFailoverResult(
                serviceId: (int) $service->id,
                fromNodeId: (int) $failedFrom->id,
                skipped: true,
                message: 'service_not_on_failed_node',
            );
        }

        if (! $this->isEligibleStatus($service->status)) {
            return new NodeFailoverResult(
                serviceId: (int) $service->id,
                fromNodeId: (int) $failedFrom->id,
                skipped: true,
                message: 'ineligible_service_status',
            );
        }

        try {
            $selection = $this->nodeSelection->selectReplacement($service, (int) $failedFrom->id);
        } catch (NoEligibleNodeException $exception) {
            $this->serviceLogger->record(
                $service,
                ServiceAction::Failover,
                ServiceActionLogStatus::Failed,
                null,
                [
                    'from_node_id' => $failedFrom->id,
                    'message' => $exception->getMessage(),
                ],
            );

            return new NodeFailoverResult(
                serviceId: (int) $service->id,
                fromNodeId: (int) $failedFrom->id,
                message: $exception->getMessage(),
            );
        }

        if (! $selection->hasNode()) {
            return new NodeFailoverResult(
                serviceId: (int) $service->id,
                fromNodeId: (int) $failedFrom->id,
                message: 'no_replacement_node',
            );
        }

        $toNodeId = (int) $selection->nodeId();

        DB::transaction(function () use ($service, $toNodeId): void {
            $service->forceFill(['node_id' => $toNodeId])->save();
        });

        $service = $service->fresh() ?? $service;
        $providerSynced = $this->syncProvider($service, $selection);

        $this->serviceLogger->record(
            $service,
            ServiceAction::Failover,
            $providerSynced ? ServiceActionLogStatus::Success : ServiceActionLogStatus::Failed,
            null,
            [
                'from_node_id' => $failedFrom->id,
                'to_node_id' => $toNodeId,
                'provider_synced' => $providerSynced,
            ],
        );

        $this->nodeLogs->record(
            node: $failedFrom,
            action: 'node.failover.service_reassigned',
            status: NodeLogStatus::Success,
            response: [
                'service_id' => $service->id,
                'to_node_id' => $toNodeId,
            ],
        );

        if ($selection->node !== null) {
            $this->nodeLogs->record(
                node: $selection->node,
                action: 'node.failover.service_received',
                status: NodeLogStatus::Success,
                response: [
                    'service_id' => $service->id,
                    'from_node_id' => $failedFrom->id,
                ],
            );
        }

        return new NodeFailoverResult(
            serviceId: (int) $service->id,
            fromNodeId: (int) $failedFrom->id,
            toNodeId: $toNodeId,
            reassigned: true,
            providerSynced: $providerSynced,
        );
    }

    public function dispatchForNode(Node $node): void
    {
        if (! $this->shouldAutoDispatch()) {
            return;
        }

        ProcessNodeFailoverJob::dispatch((int) $node->id);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Service>
     */
    private function failoverCandidates(Node $failedNode): \Illuminate\Support\Collection
    {
        return Service::query()
            ->where('node_id', $failedNode->id)
            ->whereIn('status', $this->eligibleStatuses())
            ->orderBy('id')
            ->get();
    }

    private function syncProvider(Service $service, NodeSelectionResult $selection): bool
    {
        if (! (bool) config('corepanel.nodes.failover.reinstall_on_provider', true)) {
            return true;
        }

        $module = trim((string) ($service->module ?? ''));

        if ($module === '' || ! $this->providers->hasServer($module)) {
            return true;
        }

        if (! filled($service->external_id)) {
            return true;
        }

        $response = $this->providers
            ->server($module)
            ->reinstall(ProvisioningRequest::fromService($service, $selection->connection));

        if (! $response->status->isSuccessful()) {
            return false;
        }

        DB::transaction(function () use ($service, $response): void {
            $this->mappings->syncFromProvisioningResponse($service, $response);

            $service->forceFill(array_filter([
                'external_id' => $response->externalId ?? $service->external_id,
                'hostname' => $response->hostname ?? $service->hostname,
                'ip_address' => $response->ipAddress ?? $service->ip_address,
            ], static fn (mixed $value): bool => $value !== null))->save();
        });

        return true;
    }

    private function isEligibleStatus(ServiceStatus $status): bool
    {
        return in_array($status->value, $this->eligibleStatuses(), true);
    }

    /**
     * @return list<string>
     */
    private function eligibleStatuses(): array
    {
        $configured = config('corepanel.nodes.failover.eligible_statuses', [
            ServiceStatus::Active->value,
            ServiceStatus::Suspended->value,
        ]);

        if (! is_array($configured) || $configured === []) {
            return [
                ServiceStatus::Active->value,
                ServiceStatus::Suspended->value,
            ];
        }

        return array_values(array_map(static fn (mixed $value): string => (string) $value, $configured));
    }

    private function shouldAutoDispatch(): bool
    {
        return (bool) config('corepanel.nodes.failover.enabled', true)
            && (bool) config('corepanel.nodes.failover.auto_reassign', true);
    }
}

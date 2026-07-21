<?php

namespace Core\Sync\Services;

use Core\Admin\Services\AdminNotificationService;
use Core\Nodes\Models\Node;
use Core\Services\Models\Service;
use Core\Sync\DataTransferObjects\ServiceSyncComparisonResult;
use Core\Sync\DataTransferObjects\ServiceSyncResolutionResult;
use Core\Sync\Enums\ServiceSyncDivergenceType;

class SyncAlertService
{
    public const TYPE_SERVICE_DIVERGED = 'sync.service.diverged';

    public const TYPE_SERVICE_FAILED = 'sync.service.failed';

    public const TYPE_NODE_FAILED = 'sync.node.failed';

    public function __construct(
        private readonly AdminNotificationService $notifications,
    ) {
    }

    public function handleServicePollOutcome(
        Service $service,
        string $outcome,
        ?ServiceSyncComparisonResult $comparison = null,
        ?ServiceSyncResolutionResult $resolution = null,
        ?string $message = null,
    ): void {
        if (! $this->alertsEnabled()) {
            return;
        }

        $dedupeKey = $this->serviceDedupeKey($service->id);

        if ($outcome === 'polled' || $outcome === 'resolved') {
            $this->notifications->markReadByDedupeKey($dedupeKey);

            return;
        }

        if ($outcome === 'failed') {
            $this->notifications->upsert(
                type: self::TYPE_SERVICE_FAILED,
                dedupeKey: $dedupeKey,
                title: __('Service sync failed'),
                message: $message ?? __('Unable to poll external provider for service #:id.', [
                    'id' => $service->id,
                ]),
                variant: 'danger',
                metadata: [
                    'service_id' => $service->id,
                    'module' => $service->module,
                    'hostname' => $service->hostname,
                ],
            );

            return;
        }

        if ($outcome !== 'diverged' || $comparison === null) {
            return;
        }

        $unresolved = $resolution?->unresolved ?? $comparison->divergences;

        if ($unresolved === []) {
            $this->notifications->markReadByDedupeKey($dedupeKey);

            return;
        }

        $this->notifications->upsert(
            type: self::TYPE_SERVICE_DIVERGED,
            dedupeKey: $dedupeKey,
            title: __('Service sync divergence'),
            message: $this->serviceDivergenceMessage($service, $unresolved),
            variant: 'warning',
            metadata: [
                'service_id' => $service->id,
                'module' => $service->module,
                'hostname' => $service->hostname,
                'divergences' => array_map(
                    static fn ($divergence): array => is_array($divergence)
                        ? $divergence
                        : $divergence->toArray(),
                    $unresolved,
                ),
            ],
        );
    }

    public function handleNodeSyncFailure(Node $node, ?string $message = null): void
    {
        if (! $this->alertsEnabled()) {
            return;
        }

        $this->notifications->upsert(
            type: self::TYPE_NODE_FAILED,
            dedupeKey: $this->nodeDedupeKey($node->id),
            title: __('Node sync failed'),
            message: $message ?? __('Unable to sync node :name.', [
                'name' => $node->name,
            ]),
            variant: 'danger',
            metadata: [
                'node_id' => $node->id,
                'module' => $node->module,
                'hostname' => $node->hostname,
            ],
        );
    }

    public function handleNodeSyncSuccess(Node $node): void
    {
        if (! $this->alertsEnabled()) {
            return;
        }

        $this->notifications->markReadByDedupeKey($this->nodeDedupeKey($node->id));
    }

    private function serviceDivergenceMessage(Service $service, array $divergences): string
    {
        $types = collect($divergences)
            ->map(function (mixed $divergence): ?string {
                if (is_array($divergence)) {
                    return $divergence['type'] ?? null;
                }

                return $divergence->type->value ?? null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        $labels = array_map(
            fn (string $type): string => $this->divergenceLabel($type),
            $types,
        );

        $summary = $labels !== []
            ? implode(', ', $labels)
            : __('state mismatch');

        return __('Service #:id (:hostname) diverged from provider: :summary.', [
            'id' => $service->id,
            'hostname' => $service->hostname ?? __('no hostname'),
            'summary' => $summary,
        ]);
    }

    private function divergenceLabel(string $type): string
    {
        return match ($type) {
            ServiceSyncDivergenceType::ExternalDeleted->value => __('external deletion'),
            ServiceSyncDivergenceType::StatusMismatch->value => __('status mismatch'),
            ServiceSyncDivergenceType::IpChanged->value => __('IP change'),
            ServiceSyncDivergenceType::HostnameChanged->value => __('hostname change'),
            ServiceSyncDivergenceType::ExternalIdChanged->value => __('external ID change'),
            default => $type,
        };
    }

    private function serviceDedupeKey(int $serviceId): string
    {
        return 'sync.service.'.$serviceId;
    }

    private function nodeDedupeKey(int $nodeId): string
    {
        return 'sync.node.'.$nodeId;
    }

    private function alertsEnabled(): bool
    {
        return (bool) config('corepanel.sync.alerts.enabled', true);
    }
}

<?php

namespace Core\Sync\Services;

use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeService;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\Services\ProviderRegistry;
use Core\Sync\DataTransferObjects\NodeSyncResult;
use Core\Sync\Enums\SyncLogOutcome;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class NodeSyncService
{
    public function __construct(
        private readonly NodeService $nodes,
        private readonly ProviderRegistry $providers,
        private readonly SyncLogService $logs,
        private readonly SyncAlertService $alerts,
    ) {
    }

    public function syncAll(): NodeSyncResult
    {
        if (! (bool) config('corepanel.nodes.sync.enabled', true)) {
            return new NodeSyncResult;
        }

        $synced = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($this->syncableNodes() as $node) {
            $outcome = $this->syncNode($node);

            match ($outcome) {
                'synced' => $synced++,
                'failed' => $failed++,
                default => $skipped++,
            };
        }

        return new NodeSyncResult(
            synced: $synced,
            failed: $failed,
            skipped: $skipped,
        );
    }

    /**
     * @return 'synced'|'failed'|'skipped'
     */
    public function syncNode(Node $node): string
    {
        if ($node->status === NodeStatus::Disabled) {
            return $this->finalizeNodeSync($node, 'skipped', message: 'node_disabled');
        }

        $module = trim((string) ($node->module ?? ''));

        if ($module === '') {
            return $this->finalizeNodeSync($node, 'skipped', message: 'missing_module');
        }

        if (! $this->providers->hasNode($module)) {
            Log::warning('Node sync skipped unknown provider module.', [
                'node_id' => $node->id,
                'module' => $module,
            ]);

            return $this->finalizeNodeSync($node, 'skipped', message: 'unknown_module');
        }

        try {
            $response = $this->nodes->sync($node);
        } catch (InvalidArgumentException $exception) {
            Log::warning('Node sync skipped invalid node configuration.', [
                'node_id' => $node->id,
                'module' => $module,
                'message' => $exception->getMessage(),
            ]);

            return $this->finalizeNodeSync($node, 'skipped', message: $exception->getMessage());
        }

        if (! $response->status->isSuccessful()) {
            Log::warning('Node sync failed.', [
                'node_id' => $node->id,
                'module' => $module,
                'message' => $response->message,
                'payload' => $response->payload === [] ? null : $response->payload,
            ]);

            return $this->finalizeNodeSync(
                $node,
                'failed',
                response: $response,
                message: $response->message,
            );
        }

        return $this->finalizeNodeSync($node, 'synced', response: $response);
    }

    /**
     * @return Collection<int, Node>
     */
    private function syncableNodes(): Collection
    {
        return Node::query()
            ->whereNotNull('module')
            ->where('module', '!=', '')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return 'synced'|'failed'|'skipped'
     */
    private function finalizeNodeSync(
        Node $node,
        string $outcome,
        ?NodeOperationResponse $response = null,
        ?string $message = null,
    ): string {
        $this->logs->recordNodeSync(
            node: $node,
            outcome: SyncLogOutcome::from($outcome),
            response: $response,
            message: $message,
        );

        if ($outcome === 'failed') {
            $this->alerts->handleNodeSyncFailure($node, $message ?? $response?->message);
        } elseif ($outcome === 'synced') {
            $this->alerts->handleNodeSyncSuccess($node);
        }

        return $outcome;
    }
}

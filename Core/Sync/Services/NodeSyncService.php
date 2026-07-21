<?php

namespace Core\Sync\Services;

use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeService;
use Core\Providers\Services\ProviderRegistry;
use Core\Sync\DataTransferObjects\NodeSyncResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class NodeSyncService
{
    public function __construct(
        private readonly NodeService $nodes,
        private readonly ProviderRegistry $providers,
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
            return 'skipped';
        }

        $module = trim((string) ($node->module ?? ''));

        if ($module === '') {
            return 'skipped';
        }

        if (! $this->providers->hasNode($module)) {
            Log::warning('Node sync skipped unknown provider module.', [
                'node_id' => $node->id,
                'module' => $module,
            ]);

            return 'skipped';
        }

        try {
            $response = $this->nodes->sync($node);
        } catch (InvalidArgumentException $exception) {
            Log::warning('Node sync skipped invalid node configuration.', [
                'node_id' => $node->id,
                'module' => $module,
                'message' => $exception->getMessage(),
            ]);

            return 'skipped';
        }

        if (! $response->status->isSuccessful()) {
            Log::warning('Node sync failed.', [
                'node_id' => $node->id,
                'module' => $module,
                'message' => $response->message,
                'payload' => $response->payload === [] ? null : $response->payload,
            ]);

            return 'failed';
        }

        return 'synced';
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
}

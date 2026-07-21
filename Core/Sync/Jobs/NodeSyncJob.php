<?php

namespace Core\Sync\Jobs;

use Core\Sync\Services\NodeSyncService;
use Illuminate\Foundation\Queue\Queueable;

class NodeSyncJob
{
    use Queueable;

    public function handle(NodeSyncService $sync): void
    {
        $sync->syncAll();
    }
}

<?php

namespace Core\Sync\Jobs;

use Core\Sync\Services\ServiceSyncService;
use Illuminate\Foundation\Queue\Queueable;

class ServiceSyncJob
{
    use Queueable;

    public function handle(ServiceSyncService $sync): void
    {
        $sync->pollAll();
    }
}

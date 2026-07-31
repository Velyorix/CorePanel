<?php

namespace Core\Marketplace\Jobs;

use Core\Marketplace\Services\MarketplaceUpdateChecker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckMarketplaceUpdatesJob implements ShouldQueue
{
    use Queueable;

    public function handle(MarketplaceUpdateChecker $checker): void
    {
        if (! (bool) config('corepanel.marketplace.enabled', true)) {
            return;
        }

        if (! (bool) config('corepanel.marketplace.updates.enabled', true)) {
            return;
        }

        $checker->check();
    }
}

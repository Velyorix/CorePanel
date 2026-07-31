<?php

namespace Core\Provisioning\Events;

use Core\Services\Models\Service;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when provisioning permanently fails for a service.
 */
class ServiceProvisioningFailed implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Service $service,
        public readonly ?string $reason = null,
    ) {
    }
}

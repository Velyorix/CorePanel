<?php

namespace Core\Provisioning\Events;

use Core\Services\Models\Service;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a service has been successfully provisioned (or activated from an existing mapping).
 */
class ServiceProvisioned implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Service $service,
        public readonly ?string $externalId = null,
    ) {
    }
}

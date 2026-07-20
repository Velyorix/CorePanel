<?php

namespace Core\Provisioning\Jobs;

use Core\Provisioning\Services\ProvisioningEngine;
use Core\Services\Models\Service;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued provisioning of a single service.
 *
 * Retry / backoff / dead-letter handling.
 */
class ProvisionServiceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $serviceId,
    ) {
    }

    public function handle(ProvisioningEngine $engine): void
    {
        $service = Service::query()->find($this->serviceId);

        if ($service === null) {
            return;
        }

        try {
            $engine->provision($service);
        } catch (Throwable $exception) {
            Log::error('Provisioning job failed.', [
                'service_id' => $this->serviceId,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}

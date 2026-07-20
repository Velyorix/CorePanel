<?php

namespace Core\Provisioning\Jobs;

use Core\Provisioning\Exceptions\ProvisioningAttemptFailedException;
use Core\Provisioning\Exceptions\ProvisioningException;
use Core\Provisioning\Services\ProvisioningEngine;
use Core\Providers\Exceptions\UnknownProviderException;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Services\Services\ServiceLifecycleService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued provisioning of a single service with retry, backoff, and uniqueness.
 *
 * Dead-letter handling for exhausted failures.
 */
class ProvisionServiceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries;

    /** @var list<int>|int */
    public array|int $backoff;

    public int $timeout;

    public int $uniqueFor;

    public function __construct(
        public readonly int $serviceId,
    ) {
        $this->tries = (int) config('corepanel.provisioning.tries', 3);
        $this->backoff = config('corepanel.provisioning.backoff_seconds', [30, 60, 120]);
        $this->timeout = (int) config('corepanel.provisioning.timeout_seconds', 120);
        $this->uniqueFor = (int) config('corepanel.provisioning.unique_for_seconds', 3600);
    }

    public function uniqueId(): string
    {
        return (string) $this->serviceId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->overlapKey()))
                ->dontRelease()
                ->expireAfter($this->timeout + 30),
        ];
    }

    public function handle(ProvisioningEngine $engine): void
    {
        $service = Service::query()->find($this->serviceId);

        if ($service === null) {
            return;
        }

        if ($service->status === ServiceStatus::Active && filled($service->external_id)) {
            return;
        }

        try {
            $engine->provision($service, retryableFailures: true);
        } catch (ProvisioningException|UnknownProviderException $exception) {
            Log::warning('Provisioning job aborted (non-retryable).', [
                'service_id' => $this->serviceId,
                'attempt' => $this->attempts(),
                'exception' => $exception->getMessage(),
            ]);

            $this->markServiceFailed();
            $this->fail($exception);
        } catch (ProvisioningAttemptFailedException $exception) {
            Log::warning('Provisioning attempt failed (will retry if attempts remain).', [
                'service_id' => $this->serviceId,
                'attempt' => $this->attempts(),
                'tries' => $this->tries,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Provisioning job failed unexpectedly.', [
                'service_id' => $this->serviceId,
                'attempt' => $this->attempts(),
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Provisioning job exhausted retries.', [
            'service_id' => $this->serviceId,
            'exception' => $exception?->getMessage(),
        ]);

        $this->markServiceFailed();
    }

    private function markServiceFailed(): void
    {
        $service = Service::query()->find($this->serviceId);

        if ($service === null) {
            return;
        }

        if ($service->status === ServiceStatus::Failed) {
            return;
        }

        if (! in_array($service->status, [ServiceStatus::Pending, ServiceStatus::Provisioning], true)) {
            return;
        }

        try {
            app(ServiceLifecycleService::class)->markFailed(
                $service->status === ServiceStatus::Pending
                    ? app(ServiceLifecycleService::class)->markProvisioning($service)
                    : $service,
            );
        } catch (Throwable $exception) {
            Log::error('Unable to mark service failed after provisioning job failure.', [
                'service_id' => $this->serviceId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function overlapKey(): string
    {
        return 'provisioning:service:'.$this->serviceId;
    }
}

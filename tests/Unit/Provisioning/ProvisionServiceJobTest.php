<?php

namespace Tests\Unit\Provisioning;

use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Enums\ProviderOperationStatus;
use Core\Providers\Services\ProviderRegistry;
use Core\Provisioning\Events\ServiceProvisioned;
use Core\Provisioning\Events\ServiceProvisioningFailed;
use Core\Provisioning\Exceptions\ProvisioningAttemptFailedException;
use Core\Provisioning\Jobs\ProvisionServiceJob;
use Core\Provisioning\Services\ProvisioningEngine;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProvisionServiceJobTest extends TestCase
{
    use RefreshDatabase;

    private ProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = app(ProviderRegistry::class);
        $this->registry->flush();

        config([
            'corepanel.provisioning.tries' => 3,
            'corepanel.provisioning.backoff_seconds' => [1, 2, 3],
            'corepanel.provisioning.timeout_seconds' => 60,
            'corepanel.provisioning.unique_for_seconds' => 600,
        ]);
    }

    public function test_job_reads_retry_and_backoff_from_config(): void
    {
        $job = new ProvisionServiceJob(42);

        $this->assertSame(3, $job->tries);
        $this->assertSame([1, 2, 3], $job->backoff);
        $this->assertSame(60, $job->timeout);
        $this->assertSame(600, $job->uniqueFor);
        $this->assertSame('42', $job->uniqueId());
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
    }

    public function test_job_uses_without_overlapping_middleware(): void
    {
        $job = new ProvisionServiceJob(7);
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_handle_is_idempotent_when_service_already_active(): void
    {
        $counter = (object) ['calls' => 0];
        $this->registry->registerServer($this->makeCountingProvider('stub', $counter));

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'already-there',
        ]);

        (new ProvisionServiceJob($service->id))->handle(app(ProvisioningEngine::class));

        $this->assertSame(0, $counter->calls);
        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);
    }

    public function test_handle_succeeds_and_activates_service(): void
    {
        $this->registry->registerServer($this->makeProvider(
            'stub',
            ProvisioningResponse::success(externalId: 'ext-ok'),
        ));

        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        (new ProvisionServiceJob($service->id))->handle(app(ProvisioningEngine::class));

        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);
        $this->assertSame('ext-ok', $service->fresh()->external_id);
    }

    public function test_retryable_provider_failure_leaves_service_provisioning_and_throws(): void
    {
        $this->registry->registerServer($this->makeProvider(
            'stub',
            ProvisioningResponse::failed('Transient upstream error'),
        ));

        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        $job = new ProvisionServiceJob($service->id);

        try {
            $job->handle(app(ProvisioningEngine::class));
            $this->fail('Expected ProvisioningAttemptFailedException');
        } catch (ProvisioningAttemptFailedException $exception) {
            $this->assertStringContainsString('Transient upstream error', $exception->getMessage());
        }

        $this->assertSame(ServiceStatus::Provisioning, $service->fresh()->status);
    }

    public function test_failed_callback_marks_service_failed_after_retries_exhausted(): void
    {
        Event::fake([ServiceProvisioned::class, ServiceProvisioningFailed::class]);

        $service = Service::factory()->provisioning()->create([
            'module' => 'stub',
        ]);

        $job = new ProvisionServiceJob($service->id);
        $job->failed(ProvisioningAttemptFailedException::fromMessage('gave up'));

        $this->assertSame(ServiceStatus::Failed, $service->fresh()->status);

        Event::assertDispatched(ServiceProvisioningFailed::class, function (ServiceProvisioningFailed $event) use ($service): bool {
            return $event->service->is($service) && $event->reason === 'gave up';
        });
        Event::assertNotDispatched(ServiceProvisioned::class);
    }

    public function test_failed_callback_dispatches_failure_event_only_once(): void
    {
        Event::fake([ServiceProvisioningFailed::class]);

        $service = Service::factory()->provisioning()->create([
            'module' => 'stub',
        ]);

        $job = new ProvisionServiceJob($service->id);
        $exception = ProvisioningAttemptFailedException::fromMessage('once');

        $job->failed($exception);
        $job->failed($exception);

        Event::assertDispatchedTimes(ServiceProvisioningFailed::class, 1);
    }

    public function test_non_retryable_missing_module_marks_failed_without_leaving_provisioning(): void
    {
        $service = Service::factory()->create([
            'module' => null,
            'status' => ServiceStatus::Pending,
        ]);

        $job = new class($service->id) extends ProvisionServiceJob
        {
            public bool $failedViaFail = false;

            public function fail($exception = null): void
            {
                $this->failedViaFail = true;
            }
        };

        $job->handle(app(ProvisioningEngine::class));

        $this->assertTrue($job->failedViaFail);
        $this->assertSame(ServiceStatus::Failed, $service->fresh()->status);
    }

    public function test_queue_suppresses_duplicate_unique_jobs_for_same_service(): void
    {
        Queue::fake();

        $service = Service::factory()->create(['module' => 'stub']);

        app(ProvisioningEngine::class)->queue($service);
        app(ProvisioningEngine::class)->queue($service);

        Queue::assertPushed(ProvisionServiceJob::class, 1);
        Queue::assertPushed(ProvisionServiceJob::class, function (ProvisionServiceJob $job) use ($service): bool {
            return $job->serviceId === $service->id
                && $job instanceof ShouldBeUnique
                && $job->uniqueId() === (string) $service->id;
        });
    }

    public function test_engine_retryable_failure_throws_without_marking_failed(): void
    {
        $this->registry->registerServer($this->makeProvider(
            'stub',
            ProvisioningResponse::failed('retry me'),
        ));

        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        $this->expectException(ProvisioningAttemptFailedException::class);

        try {
            app(ProvisioningEngine::class)->provision($service, retryableFailures: true);
        } finally {
            $this->assertSame(ServiceStatus::Provisioning, $service->fresh()->status);
        }
    }

    public function test_engine_sync_failure_still_marks_failed(): void
    {
        $this->registry->registerServer($this->makeProvider(
            'stub',
            ProvisioningResponse::failed('permanent'),
        ));

        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        $response = app(ProvisioningEngine::class)->provision($service);

        $this->assertSame(ProviderOperationStatus::Failed, $response->status);
        $this->assertSame(ServiceStatus::Failed, $service->fresh()->status);
    }

    private function makeProvider(string $key, ProvisioningResponse $response): ServerProviderInterface
    {
        return new class($key, $response) implements ServerProviderInterface
        {
            public function __construct(
                private readonly string $keyValue,
                private readonly ProvisioningResponse $response,
            ) {
            }

            public function key(): string
            {
                return $this->keyValue;
            }

            public function label(): string
            {
                return strtoupper($this->keyValue);
            }

            public function create(ProvisioningRequest $request): ProvisioningResponse
            {
                return $this->response;
            }

            public function suspend(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function unsuspend(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function terminate(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function reinstall(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }
        };
    }

    private function makeCountingProvider(string $key, object $counter): ServerProviderInterface
    {
        return new class($key, $counter) implements ServerProviderInterface
        {
            public function __construct(
                private readonly string $keyValue,
                private readonly object $counter,
            ) {
            }

            public function key(): string
            {
                return $this->keyValue;
            }

            public function label(): string
            {
                return strtoupper($this->keyValue);
            }

            public function create(ProvisioningRequest $request): ProvisioningResponse
            {
                $this->counter->calls++;

                return ProvisioningResponse::success(externalId: 'should-not-run');
            }

            public function suspend(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function unsuspend(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function terminate(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function reinstall(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }
        };
    }
}

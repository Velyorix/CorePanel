<?php

namespace Tests\Unit\Provisioning;

use Core\Provisioning\Enums\ProvisioningDeadLetterStatus;
use Core\Provisioning\Exceptions\ProvisioningAttemptFailedException;
use Core\Provisioning\Jobs\ProvisionServiceJob;
use Core\Provisioning\Models\ProvisioningDeadLetter;
use Core\Provisioning\Services\ProvisioningDeadLetterService;
use Core\Provisioning\Services\ProvisioningEngine;
use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Services\ProviderRegistry;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class ProvisioningDeadLetterTest extends TestCase
{
    use RefreshDatabase;

    private ProviderRegistry $registry;

    private ProvisioningDeadLetterService $deadLetters;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = app(ProviderRegistry::class);
        $this->registry->flush();
        $this->deadLetters = app(ProvisioningDeadLetterService::class);
    }

    public function test_dead_letter_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ProvisioningDeadLetterService::class),
            app(ProvisioningDeadLetterService::class),
        );
    }

    public function test_record_creates_pending_review_entry(): void
    {
        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Failed,
        ]);

        $letter = $this->deadLetters->record(
            serviceId: $service->id,
            exception: ProvisioningAttemptFailedException::fromMessage('upstream down'),
            attempts: 3,
            payload: ['tries' => 3],
        );

        $this->assertSame(ProvisioningDeadLetterStatus::PendingReview, $letter->status);
        $this->assertSame($service->id, $letter->service_id);
        $this->assertSame('stub', $letter->module);
        $this->assertSame(3, $letter->attempts);
        $this->assertSame(ProvisioningAttemptFailedException::class, $letter->exception_class);
        $this->assertSame('upstream down', $letter->exception_message);
        $this->assertSame(['tries' => 3], $letter->payload);
        $this->assertNotNull($letter->failed_at);
    }

    public function test_record_is_idempotent_for_open_letter(): void
    {
        $service = Service::factory()->create(['module' => 'stub']);

        $first = $this->deadLetters->record($service->id, attempts: 1);
        $second = $this->deadLetters->record(
            $service->id,
            ProvisioningAttemptFailedException::fromMessage('retry exhausted'),
            attempts: 3,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ProvisioningDeadLetter::query()->count());
        $this->assertSame(3, $second->attempts);
        $this->assertSame('retry exhausted', $second->exception_message);
    }

    public function test_job_failed_callback_persists_dead_letter_and_marks_service_failed(): void
    {
        $service = Service::factory()->provisioning()->create([
            'module' => 'stub',
        ]);

        $job = new ProvisionServiceJob($service->id);
        $job->failed(ProvisioningAttemptFailedException::fromMessage('gave up'));

        $this->assertSame(ServiceStatus::Failed, $service->fresh()->status);

        $letter = ProvisioningDeadLetter::query()->where('service_id', $service->id)->firstOrFail();
        $this->assertSame(ProvisioningDeadLetterStatus::PendingReview, $letter->status);
        $this->assertSame('gave up', $letter->exception_message);
    }

    public function test_non_retryable_failure_records_dead_letter(): void
    {
        $service = Service::factory()->create([
            'module' => null,
            'status' => ServiceStatus::Pending,
        ]);

        $job = new class($service->id) extends ProvisionServiceJob
        {
            public function fail($exception = null): void
            {
            }
        };

        $job->handle(app(ProvisioningEngine::class));

        $this->assertSame(ServiceStatus::Failed, $service->fresh()->status);
        $this->assertDatabaseHas('provisioning_dead_letters', [
            'service_id' => $service->id,
            'status' => ProvisioningDeadLetterStatus::PendingReview->value,
        ]);
    }

    public function test_requeue_closes_letter_and_dispatches_job(): void
    {
        Queue::fake();

        $service = Service::factory()->failed()->create([
            'module' => 'stub',
        ]);

        $letter = $this->deadLetters->record($service->id, attempts: 3);

        $updated = $this->deadLetters->requeue($letter, notes: 'manual retry');

        $this->assertSame(ProvisioningDeadLetterStatus::Requeued, $updated->status);
        $this->assertSame('manual retry', $updated->resolution_notes);
        $this->assertNotNull($updated->resolved_at);

        Queue::assertPushed(ProvisionServiceJob::class, function (ProvisionServiceJob $job) use ($service): bool {
            return $job->serviceId === $service->id;
        });
    }

    public function test_resolve_and_discard_close_open_letters(): void
    {
        $service = Service::factory()->failed()->create(['module' => 'stub']);

        $resolved = $this->deadLetters->resolve(
            $this->deadLetters->record($service->id),
            notes: 'fixed manually',
        );
        $this->assertSame(ProvisioningDeadLetterStatus::Resolved, $resolved->status);

        $other = Service::factory()->failed()->create(['module' => 'stub']);
        $discarded = $this->deadLetters->discard(
            $this->deadLetters->record($other->id),
            notes: 'cancelled order',
        );
        $this->assertSame(ProvisioningDeadLetterStatus::Discarded, $discarded->status);
    }

    public function test_requeue_rejects_closed_letters(): void
    {
        $service = Service::factory()->failed()->create(['module' => 'stub']);
        $letter = $this->deadLetters->resolve($this->deadLetters->record($service->id));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only pending_review dead letters can be requeued.');

        $this->deadLetters->requeue($letter);
    }

    public function test_pending_lists_open_letters_only(): void
    {
        $openService = Service::factory()->failed()->create(['module' => 'a']);
        $closedService = Service::factory()->failed()->create(['module' => 'b']);

        $this->deadLetters->record($openService->id);
        $this->deadLetters->resolve($this->deadLetters->record($closedService->id));

        $pending = $this->deadLetters->pending();

        $this->assertCount(1, $pending);
        $this->assertSame($openService->id, $pending->first()->service_id);
    }

    public function test_exhausted_retries_end_in_dead_letter_via_sync_queue(): void
    {
        config(['corepanel.provisioning.tries' => 1]);

        $this->registry->registerServer($this->makeProvider(
            'stub',
            ProvisioningResponse::failed('always fails'),
        ));

        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        try {
            (new ProvisionServiceJob($service->id))->handle(app(ProvisioningEngine::class));
            $this->fail('Expected retryable failure');
        } catch (ProvisioningAttemptFailedException) {
            (new ProvisionServiceJob($service->id))->failed(
                ProvisioningAttemptFailedException::fromMessage('always fails'),
            );
        }

        $this->assertSame(ServiceStatus::Failed, $service->fresh()->status);
        $this->assertDatabaseHas('provisioning_dead_letters', [
            'service_id' => $service->id,
            'status' => ProvisioningDeadLetterStatus::PendingReview->value,
            'module' => 'stub',
        ]);
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
}

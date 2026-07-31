<?php

namespace Tests\Feature\Services;

use Core\Auth\Models\User;
use Core\Services\Contracts\ModuleActionDispatcher;
use Core\Services\Contracts\ServiceActionLogger;
use Core\Services\Enums\ServiceAction;
use Core\Services\Enums\ServiceActionLogStatus;
use Core\Services\Models\Service;
use Core\Services\Models\ServiceActionLog;
use Core\Services\Services\ServiceActionLogService;
use Core\Services\Services\ServiceControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ServiceActionLogServiceTest extends TestCase
{
    use RefreshDatabase;

    private ServiceActionLogService $logs;

    private ServiceActionLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logs = app(ServiceActionLogService::class);
        $this->logger = app(ServiceActionLogger::class);
    }

    public function test_service_action_log_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ServiceActionLogService::class),
            app(ServiceActionLogService::class),
        );
    }

    public function test_for_service_lists_newest_first_and_filters(): void
    {
        $service = Service::factory()->create();
        $other = Service::factory()->create();

        ServiceActionLog::factory()->forService($other)->action(ServiceAction::Start)->create();
        ServiceActionLog::factory()->forService($service)->action(ServiceAction::Start)->success()->create([
            'created_at' => now()->subMinutes(3),
        ]);
        ServiceActionLog::factory()->forService($service)->action(ServiceAction::Suspend)->success()->create([
            'created_at' => now()->subMinutes(2),
        ]);
        ServiceActionLog::factory()->forService($service)->action(ServiceAction::Start)->failed()->create([
            'created_at' => now()->subMinute(),
        ]);

        $all = $this->logs->forService($service);
        $this->assertCount(3, $all);
        $this->assertSame(ServiceAction::Start, $all->first()->action);
        $this->assertSame(ServiceActionLogStatus::Failed, $all->first()->status);

        $starts = $this->logs->forService($service, action: ServiceAction::Start);
        $this->assertCount(2, $starts);
        $this->assertTrue($starts->every(fn (ServiceActionLog $log): bool => $log->action === ServiceAction::Start));

        $failed = $this->logs->forService($service, status: ServiceActionLogStatus::Failed);
        $this->assertCount(1, $failed);
        $this->assertSame(ServiceActionLogStatus::Failed, $failed->first()->status);

        $limited = $this->logs->forService($service, limit: 1);
        $this->assertCount(1, $limited);
    }

    public function test_latest_returns_most_recent_log(): void
    {
        $service = Service::factory()->create();

        ServiceActionLog::factory()->forService($service)->action(ServiceAction::Stop)->create([
            'created_at' => now()->subHour(),
        ]);
        $latest = ServiceActionLog::factory()->forService($service)->action(ServiceAction::Restart)->create([
            'created_at' => now(),
        ]);

        $found = $this->logs->latest($service);

        $this->assertNotNull($found);
        $this->assertSame($latest->id, $found->id);
        $this->assertSame(ServiceAction::Restart, $found->action);
    }

    public function test_logger_persists_performed_by_and_response(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create();

        $log = $this->logger->record(
            $service,
            ServiceAction::Restart,
            ServiceActionLogStatus::Success,
            $user->id,
            ['module' => 'stub', 'ok' => true],
        );

        $this->assertSame($service->id, $log->service_id);
        $this->assertSame(ServiceAction::Restart, $log->action);
        $this->assertSame(ServiceActionLogStatus::Success, $log->status);
        $this->assertSame($user->id, $log->performed_by);
        $this->assertSame(['module' => 'stub', 'ok' => true], $log->response);
        $this->assertNotNull($log->created_at);
        $this->assertTrue($log->performer->is($user));
    }

    public function test_control_action_with_user_sets_performed_by(): void
    {
        $service = Service::factory()->active()->create();
        $user = User::factory()->create();

        app(ServiceControlService::class)->restart($service, $user);

        $log = $this->logs->latest($service);

        $this->assertNotNull($log);
        $this->assertSame(ServiceAction::Restart, $log->action);
        $this->assertSame($user->id, $log->performed_by);
        $this->assertSame(ServiceActionLogStatus::Skipped, $log->status);
    }

    public function test_control_module_failure_logs_failed_status(): void
    {
        $this->app->instance(ModuleActionDispatcher::class, new class implements ModuleActionDispatcher
        {
            public function dispatch(Service $service, ServiceAction $action): array
            {
                return [
                    'status' => 'failed',
                    'response' => [
                        'reason' => 'Panel unreachable.',
                        'action' => $action->value,
                    ],
                ];
            }
        });
        $this->app->forgetInstance(ServiceControlService::class);

        $service = Service::factory()->active()->create();
        $control = app(ServiceControlService::class);

        try {
            $control->restart($service);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Module action [restart] failed', $exception->getMessage());
        }

        $log = $this->logs->latest($service);

        $this->assertNotNull($log);
        $this->assertSame(ServiceAction::Restart, $log->action);
        $this->assertSame(ServiceActionLogStatus::Failed, $log->status);
        $this->assertSame('Panel unreachable.', $log->response['reason'] ?? null);
        $this->assertNull($log->performed_by);
    }
}

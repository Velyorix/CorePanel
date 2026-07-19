<?php

namespace Tests\Feature\Billing;

use App\Jobs\ProcessOverdueSuspensions;
use Core\Billing\DataTransferObjects\SuspensionProcessingResult;
use Core\Billing\Services\OverdueSuspensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProcessOverdueSuspensionsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_delegates_to_suspension_service(): void
    {
        $service = \Mockery::mock(OverdueSuspensionService::class);
        $service->shouldReceive('process')
            ->once()
            ->andReturn(new SuspensionProcessingResult);

        $this->app->instance(OverdueSuspensionService::class, $service);

        app(ProcessOverdueSuspensions::class)->handle($service);
    }

    public function test_overdue_suspension_job_is_scheduled_daily_by_default(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString(ProcessOverdueSuspensions::class, $output);
        $this->assertMatchesRegularExpression('/0\s+0\s+\*\s+\*\s+\*/', $output);
    }
}

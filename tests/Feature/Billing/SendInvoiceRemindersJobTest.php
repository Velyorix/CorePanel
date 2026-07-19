<?php

namespace Tests\Feature\Billing;

use App\Jobs\SendInvoiceReminders;
use Core\Billing\DataTransferObjects\ReminderProcessingResult;
use Core\Billing\Services\InvoiceReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SendInvoiceRemindersJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_delegates_to_reminder_service(): void
    {
        $service = \Mockery::mock(InvoiceReminderService::class);
        $service->shouldReceive('process')
            ->once()
            ->andReturn(new ReminderProcessingResult);

        $this->app->instance(InvoiceReminderService::class, $service);

        app(SendInvoiceReminders::class)->handle($service);
    }

    public function test_invoice_reminder_job_is_scheduled_daily_by_default(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString(SendInvoiceReminders::class, $output);
        $this->assertMatchesRegularExpression('/0\s+0\s+\*\s+\*\s+\*/', $output);
    }
}

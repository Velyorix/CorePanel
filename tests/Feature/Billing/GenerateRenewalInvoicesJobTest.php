<?php

namespace Tests\Feature\Billing;

use App\Jobs\GenerateRenewalInvoices;
use Core\Billing\Services\RenewalInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GenerateRenewalInvoicesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_completes_without_error_with_null_source(): void
    {
        $service = \Mockery::mock(RenewalInvoiceService::class);
        $service->shouldReceive('generateDue')
            ->once()
            ->andReturn(new \Core\Billing\DataTransferObjects\RenewalGenerationResult);

        $this->app->instance(RenewalInvoiceService::class, $service);

        app(GenerateRenewalInvoices::class)->handle($service);
    }

    public function test_renewal_invoice_job_is_scheduled_daily_by_default(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString(GenerateRenewalInvoices::class, $output);
        $this->assertMatchesRegularExpression('/0\s+0\s+\*\s+\*\s+\*/', $output);
    }
}

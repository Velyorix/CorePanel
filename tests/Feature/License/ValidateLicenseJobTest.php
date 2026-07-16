<?php

namespace Tests\Feature\License;

use App\Jobs\ValidateLicenseJob;
use Core\License\Services\LicenseValidationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ValidateLicenseJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_job_forces_license_validation_refresh(): void
    {
        $service = \Mockery::mock(LicenseValidationService::class);
        $service->shouldReceive('validate')
            ->once()
            ->with(true);

        $this->app->instance(LicenseValidationService::class, $service);

        app(ValidateLicenseJob::class)->handle($service);
    }

    public function test_license_validation_job_is_scheduled_as_periodic_heartbeat(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('0 */6 * * *', $output);
        $this->assertStringContainsString(ValidateLicenseJob::class, $output);
    }
}


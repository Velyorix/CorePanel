<?php

namespace App\Jobs;

use Core\License\Services\LicenseValidationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ValidateLicenseJob implements ShouldQueue
{
    use Queueable;

    public function handle(LicenseValidationService $licenseValidationService): void
    {
        $licenseValidationService->validate(forceRefresh: true);
    }
}


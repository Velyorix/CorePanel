<?php

use App\Jobs\ValidateLicenseJob;
use App\Jobs\GenerateRenewalInvoices;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ValidateLicenseJob)
    ->everySixHours()
    ->withoutOverlapping();

$renewalSchedule = (string) config('corepanel.billing.renewal.schedule', 'daily');

$renewal = Schedule::job(new GenerateRenewalInvoices)->withoutOverlapping();

match ($renewalSchedule) {
    'hourly' => $renewal->hourly(),
    default => $renewal->daily(),
};
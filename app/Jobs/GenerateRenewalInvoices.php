<?php

namespace App\Jobs;

use Core\Billing\Services\RenewalInvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateRenewalInvoices implements ShouldQueue
{
    use Queueable;

    public function handle(RenewalInvoiceService $renewals): void
    {
        $renewals->generateDue();
    }
}

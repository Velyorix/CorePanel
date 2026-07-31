<?php

namespace Core\Automation\Jobs;

use Core\Billing\Services\InvoiceReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckInvoicesDueJob implements ShouldQueue
{
    use Queueable;

    public function handle(InvoiceReminderService $reminders): void
    {
        if (! (bool) config('corepanel.billing.reminders.enabled', true)) {
            return;
        }

        $reminders->markOverdue(now());
    }
}

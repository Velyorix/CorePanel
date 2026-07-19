<?php

namespace App\Jobs;

use Core\Billing\Services\InvoiceReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendInvoiceReminders implements ShouldQueue
{
    use Queueable;

    public function handle(InvoiceReminderService $reminders): void
    {
        $reminders->process();
    }
}

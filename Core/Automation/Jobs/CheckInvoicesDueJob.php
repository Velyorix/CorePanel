<?php

namespace Core\Automation\Jobs;

use Core\Automation\Concerns\IdempotentUniqueJob;
use Core\Automation\Services\AutomationIdempotencyKey;
use Core\Billing\Services\InvoiceReminderService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckInvoicesDueJob implements ShouldBeUnique, ShouldQueue
{
    use IdempotentUniqueJob;
    use Queueable;

    public function __construct()
    {
        $this->configureUniqueFor();
    }

    public function idempotencyKey(): string
    {
        return app(AutomationIdempotencyKey::class)->forJob(self::class);
    }

    public function handle(InvoiceReminderService $reminders): void
    {
        if (! (bool) config('corepanel.billing.reminders.enabled', true)) {
            return;
        }

        $reminders->markOverdue(now());
    }
}

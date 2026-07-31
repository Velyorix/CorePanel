<?php

namespace Core\Billing\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Core\Billing\DataTransferObjects\ReminderProcessingResult;
use Core\Billing\Enums\InvoiceReminderLevel;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Events\InvoiceOverdue;
use Core\Billing\Models\Invoice;
use Core\Billing\Notifications\InvoiceReminderNotification;
use Core\Notifications\Services\NotificationPreferenceService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Marks overdue invoices and sends staged payment reminder emails.
 * Does not suspend services (handled separately).
 */
class InvoiceReminderService
{
    public function __construct(
        private readonly NotificationPreferenceService $preferences,
    ) {
    }

    public function process(?CarbonInterface $asOf = null): ReminderProcessingResult
    {
        if (! (bool) config('corepanel.billing.reminders.enabled', true)) {
            return new ReminderProcessingResult;
        }

        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $result = new ReminderProcessingResult;

        $marked = $this->markOverdue($asOf);
        $result = $result->withMarkedOverdue($marked);

        $levels = $this->configuredLevels();

        if ($levels === []) {
            return $result;
        }

        $invoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Overdue])
            ->whereNotNull('due_at')
            ->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            try {
                if ((float) $invoice->amountDue() <= 0) {
                    $result = $result->withSkipped();

                    continue;
                }

                $email = trim((string) $invoice->contact_email);

                if ($email === '') {
                    $result = $result->withSkipped();

                    continue;
                }

                $daysFromDue = (int) $invoice->due_at->copy()->startOfDay()->diffInDays($asOf, false);
                $currentLevel = (int) $invoice->reminder_level;
                $sentThisRun = false;

                foreach ($levels as $index => $level) {
                    $levelNumber = $index + 1;

                    if ($levelNumber <= $currentLevel) {
                        continue;
                    }

                    if ($daysFromDue < (int) $level['days_offset']) {
                        break;
                    }

                    if (! $this->maySendBillingMail($invoice, $email)) {
                        $result = $result->withSkipped();
                        $sentThisRun = true;
                        break;
                    }

                    Notification::route('mail', $email)
                        ->notify(new InvoiceReminderNotification($invoice, $level['key']));

                    $invoice->forceFill([
                        'reminder_level' => $levelNumber,
                        'last_reminder_at' => now(),
                        'reminders_sent' => (int) $invoice->reminders_sent + 1,
                    ])->save();

                    $currentLevel = $levelNumber;
                    $sentThisRun = true;
                    $result = $result->withSent();

                    // One reminder email per invoice per process run.
                    break;
                }

                if (! $sentThisRun) {
                    $result = $result->withSkipped();
                }
            } catch (Throwable $exception) {
                Log::warning('Invoice reminder failed.', [
                    'invoice_id' => $invoice->id,
                    'message' => $exception->getMessage(),
                ]);

                $result = $result->withError();
            }
        }

        return $result;
    }

    public function markOverdue(CarbonInterface $asOf): int
    {
        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::Unpaid)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $asOf->copy()->startOfDay())
            ->orderBy('id')
            ->get();

        $count = 0;

        foreach ($invoices as $invoice) {
            $invoice->forceFill(['status' => InvoiceStatus::Overdue])->save();
            event(new InvoiceOverdue($invoice->fresh() ?? $invoice));
            $count++;
        }

        return $count;
    }

    /**
     * @return list<array{key: InvoiceReminderLevel, days_offset: int}>
     */
    public function configuredLevels(): array
    {
        $raw = config('corepanel.billing.reminders.levels', []);

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $levels = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = InvoiceReminderLevel::tryFrom((string) ($entry['key'] ?? ''));

            if ($key === null || ! array_key_exists('days_offset', $entry)) {
                continue;
            }

            $levels[] = [
                'key' => $key,
                'days_offset' => (int) $entry['days_offset'],
            ];
        }

        usort(
            $levels,
            static fn (array $a, array $b): int => $a['days_offset'] <=> $b['days_offset'],
        );

        return array_values($levels);
    }

    private function maySendBillingMail(Invoice $invoice, string $email): bool
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $invoice->loadMissing('client');
            $ownerId = $invoice->client?->user_id;

            if ($ownerId !== null) {
                $user = User::query()->find($ownerId);
            }
        }

        if (! $user instanceof User) {
            return true;
        }

        return $this->preferences->allows($user, NotificationPreferenceService::CHANNEL_MAIL, 'billing');
    }
}

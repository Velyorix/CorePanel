<?php

namespace Core\Billing\Notifications;

use Core\Billing\Enums\InvoiceReminderLevel;
use Core\Billing\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly InvoiceReminderLevel $level,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->invoice->invoice_number ?? ('#'.$this->invoice->id);
        $amountDue = $this->invoice->amountDue();
        $currency = $this->invoice->currency ?? 'EUR';
        $dueAt = $this->invoice->due_at?->toDateString() ?? '—';
        $appName = (string) config('corepanel.name', config('app.name'));

        return (new MailMessage)
            ->subject($this->subjectForLevel($number))
            ->markdown('mail.billing.invoice-reminder', [
                'heading' => $this->level->label(),
                'lines' => $this->linesForLevel($number, $amountDue, $currency, $dueAt),
                'footer' => __('If you have already paid, please disregard this message.'),
                'appName' => $appName,
            ]);
    }

    private function subjectForLevel(string $number): string
    {
        return match ($this->level) {
            InvoiceReminderLevel::BeforeDue7 => __('Reminder: invoice :number is due in 7 days', ['number' => $number]),
            InvoiceReminderLevel::BeforeDue3 => __('Reminder: invoice :number is due in 3 days', ['number' => $number]),
            InvoiceReminderLevel::Due => __('Invoice :number is due today', ['number' => $number]),
            InvoiceReminderLevel::Overdue => __('Overdue invoice :number', ['number' => $number]),
            InvoiceReminderLevel::FinalWarning => __('Final warning: unpaid invoice :number', ['number' => $number]),
        };
    }

    /**
     * @return list<string>
     */
    private function linesForLevel(string $number, string $amountDue, string $currency, string $dueAt): array
    {
        $amountLine = __('Amount due: :amount :currency', [
            'amount' => $amountDue,
            'currency' => $currency,
        ]);
        $dueLine = __('Due date: :date', ['date' => $dueAt]);

        return match ($this->level) {
            InvoiceReminderLevel::BeforeDue7 => [
                __('This is a reminder that invoice :number will be due in 7 days.', ['number' => $number]),
                $dueLine,
                $amountLine,
            ],
            InvoiceReminderLevel::BeforeDue3 => [
                __('Invoice :number will be due in 3 days.', ['number' => $number]),
                $dueLine,
                $amountLine,
            ],
            InvoiceReminderLevel::Due => [
                __('Invoice :number is due today.', ['number' => $number]),
                $amountLine,
            ],
            InvoiceReminderLevel::Overdue => [
                __('Invoice :number is now overdue.', ['number' => $number]),
                $dueLine,
                $amountLine,
                __('Please arrange payment as soon as possible.'),
            ],
            InvoiceReminderLevel::FinalWarning => [
                __('Final warning for unpaid invoice :number.', ['number' => $number]),
                $dueLine,
                $amountLine,
                __('Services may be suspended if payment is not received soon.'),
            ],
        };
    }
}

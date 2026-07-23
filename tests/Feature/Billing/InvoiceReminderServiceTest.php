<?php

namespace Tests\Feature\Billing;

use Carbon\Carbon;
use App\Models\User;
use Core\Billing\Enums\InvoiceReminderLevel;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Models\Invoice;
use Core\Billing\Notifications\InvoiceReminderNotification;
use Core\Billing\Services\InvoiceReminderService;
use Core\Notifications\Services\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvoiceReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceReminderService $reminders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reminders = app(InvoiceReminderService::class);

        config([
            'corepanel.billing.reminders.enabled' => true,
        ]);
    }

    public function test_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(InvoiceReminderService::class),
            app(InvoiceReminderService::class),
        );
    }

    public function test_disabled_config_skips_processing(): void
    {
        Notification::fake();
        config(['corepanel.billing.reminders.enabled' => false]);

        Invoice::factory()->unpaid()->create([
            'total_amount' => '50.00',
            'subtotal' => '50.00',
            'due_at' => Carbon::parse('2026-01-01'),
            'contact_email' => 'client@example.test',
        ]);

        $result = $this->reminders->process(Carbon::parse('2026-01-10'));

        $this->assertSame(0, $result->markedOverdue);
        $this->assertSame(0, $result->sent);
        Notification::assertNothingSent();
    }

    public function test_marks_unpaid_past_due_as_overdue(): void
    {
        Notification::fake();

        $invoice = Invoice::factory()->unpaid()->create([
            'total_amount' => '40.00',
            'subtotal' => '40.00',
            'due_at' => Carbon::parse('2026-01-05'),
            'contact_email' => 'overdue@example.test',
            'reminder_level' => 5,
        ]);

        $result = $this->reminders->process(Carbon::parse('2026-01-10'));

        $this->assertSame(1, $result->markedOverdue);
        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
    }

    public function test_sends_before_due_reminder_and_is_idempotent(): void
    {
        Notification::fake();

        $invoice = Invoice::factory()->unpaid()->create([
            'total_amount' => '100.00',
            'subtotal' => '100.00',
            'due_at' => Carbon::parse('2026-01-20'),
            'contact_email' => 'soon@example.test',
            'reminder_level' => 0,
        ]);

        $asOf = Carbon::parse('2026-01-13'); // 7 days before due
        $first = $this->reminders->process($asOf);

        $this->assertSame(1, $first->sent);
        $invoice->refresh();
        $this->assertSame(1, $invoice->reminder_level);
        $this->assertSame(1, $invoice->reminders_sent);
        $this->assertNotNull($invoice->last_reminder_at);

        Notification::assertSentOnDemand(
            InvoiceReminderNotification::class,
            function (InvoiceReminderNotification $notification, array $channels, object $notifiable) use ($invoice): bool {
                return ($notifiable->routes['mail'] ?? null) === 'soon@example.test'
                    && $notification->invoice->is($invoice)
                    && $notification->level === InvoiceReminderLevel::BeforeDue7
                    && in_array('mail', $channels, true);
            },
        );

        Notification::fake();
        $second = $this->reminders->process($asOf);

        $this->assertSame(0, $second->sent);
        $this->assertSame(1, $second->skipped);
        $this->assertSame(1, $invoice->fresh()->reminder_level);
        Notification::assertNothingSent();
    }

    public function test_sends_overdue_reminder_after_due_date(): void
    {
        Notification::fake();

        $invoice = Invoice::factory()->unpaid()->create([
            'total_amount' => '25.00',
            'subtotal' => '25.00',
            'due_at' => Carbon::parse('2026-02-01'),
            'contact_email' => 'late@example.test',
            'reminder_level' => 3, // due reminder already sent
        ]);

        $result = $this->reminders->process(Carbon::parse('2026-02-02'));

        $this->assertSame(1, $result->markedOverdue);
        $this->assertSame(1, $result->sent);
        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
        $this->assertSame(4, $invoice->fresh()->reminder_level);

        Notification::assertSentOnDemand(
            InvoiceReminderNotification::class,
            fn (InvoiceReminderNotification $notification): bool => $notification->level === InvoiceReminderLevel::Overdue,
        );
    }

    public function test_skips_invoices_without_balance_or_email(): void
    {
        Notification::fake();

        Invoice::factory()->unpaid()->create([
            'total_amount' => '0.00',
            'subtotal' => '0.00',
            'due_at' => Carbon::parse('2026-01-20'),
            'contact_email' => 'zero@example.test',
        ]);

        Invoice::factory()->unpaid()->create([
            'total_amount' => '10.00',
            'subtotal' => '10.00',
            'due_at' => Carbon::parse('2026-01-20'),
            'contact_email' => '',
        ]);

        $result = $this->reminders->process(Carbon::parse('2026-01-13'));

        $this->assertSame(0, $result->sent);
        $this->assertSame(2, $result->skipped);
        Notification::assertNothingSent();
    }

    public function test_skips_reminder_when_user_disabled_billing_mail(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'optout@example.test']);
        app(NotificationPreferenceService::class)->update($user, [
            'channels' => ['mail' => true, 'database' => true],
            'categories' => [
                'billing' => ['mail' => false, 'database' => true],
                'tickets' => ['mail' => true, 'database' => true],
                'services' => ['mail' => true, 'database' => true],
            ],
        ]);

        $invoice = Invoice::factory()->unpaid()->create([
            'total_amount' => '100.00',
            'subtotal' => '100.00',
            'due_at' => Carbon::parse('2026-01-20'),
            'contact_email' => 'optout@example.test',
            'reminder_level' => 0,
        ]);

        $result = $this->reminders->process(Carbon::parse('2026-01-13'));

        $this->assertSame(0, $result->sent);
        $this->assertSame(1, $result->skipped);
        $this->assertSame(0, $invoice->fresh()->reminder_level);
        Notification::assertNothingSent();
    }

    public function test_configured_levels_match_cdc_offsets(): void
    {
        $levels = $this->reminders->configuredLevels();

        $this->assertCount(5, $levels);
        $this->assertSame(InvoiceReminderLevel::BeforeDue7, $levels[0]['key']);
        $this->assertSame(-7, $levels[0]['days_offset']);
        $this->assertSame(InvoiceReminderLevel::FinalWarning, $levels[4]['key']);
        $this->assertSame(2, $levels[4]['days_offset']);
        $this->assertSame(
            ['before_due_7', 'before_due_3', 'due', 'overdue', 'final_warning'],
            InvoiceReminderLevel::values(),
        );
    }
}

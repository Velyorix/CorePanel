<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Carbon\Carbon;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\InvoiceReminderService;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Notifications\Notifications\ChannelNotification;
use Core\Notifications\Services\NotificationService;
use Core\Settings\Models\Setting;
use Core\Settings\Services\SettingsService;
use Core\Tickets\Events\TicketReplied;
use Core\Tickets\Listeners\NotifyParticipantsOnTicketReplied;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Notifications\TicketReplyNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * End-to-end smoke for notifications delivery, encrypted settings, and client opt-out.
 */
class NotificationsSettingsAcceptanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'cache.default' => 'array',
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.notif-settings-acceptance',
            'corepanel.settings.cache.enabled' => true,
            'corepanel.settings.cache.store' => 'array',
            'corepanel.settings.cache.prefix' => 'test.settings.acceptance',
            'corepanel.settings.cache.ttl_seconds' => 3600,
            'corepanel.notifications.enabled' => true,
            'corepanel.notifications.default_channels' => ['mail', 'database'],
            'corepanel.notifications.queue_by_default' => false,
            'corepanel.tickets.notifications.enabled' => true,
            'corepanel.billing.reminders.enabled' => true,
            'corepanel.locale.supported' => ['en', 'fr'],
        ]);

        app(SettingsService::class)->flushCache();
    }

    public function test_notification_is_delivered_on_mail_and_database_channels(): void
    {
        $user = User::factory()->withRole('client')->create();

        app(NotificationService::class)->send(
            $user,
            ChannelNotification::make(
                title: 'Service ready',
                message: 'Your VPS is online.',
                channels: ['database'],
                category: 'services',
            ),
        );

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'type' => ChannelNotification::class,
        ]);

        $row = $user->notifications()->first();
        $this->assertNotNull($row);
        $this->assertSame('Service ready', $row->data['title'] ?? null);
        $this->assertSame('services', $row->data['category'] ?? null);

        Notification::fake();

        app(NotificationService::class)->send(
            $user,
            ChannelNotification::make(
                title: 'Service ready',
                message: 'Your VPS is online.',
                channels: ['mail', 'database'],
                category: 'services',
            ),
        );

        Notification::assertSentTo(
            $user,
            ChannelNotification::class,
            function (ChannelNotification $notification, array $channels): bool {
                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && $notification->title === 'Service ready';
            },
        );
    }

    public function test_admin_encrypted_mail_setting_round_trips_and_invalidates_cache(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $settings = app(SettingsService::class);

        $this->actingAs($admin)
            ->from(route('admin.settings.mail'))
            ->put(route('admin.settings.mail.update'), [
                'host' => 'smtp.example.com',
                'port' => 587,
                'username' => 'mailer',
                'password' => 'first-secret',
                'encryption' => 'tls',
                'from_address' => 'noreply@example.com',
                'from_name' => 'CorePanel',
            ])
            ->assertRedirect(route('admin.settings.mail'))
            ->assertSessionHas('status');

        $this->assertSame('first-secret', $settings->getEncrypted('mail.password'));
        $this->assertSame('smtp.example.com', $settings->getString('mail.host'));

        Setting::query()->where('key', 'mail.host')->update([
            'value' => 'stale.example.com',
            'updated_at' => now(),
        ]);

        $this->assertSame('smtp.example.com', $settings->getString('mail.host'));

        $this->actingAs($admin)
            ->put(route('admin.settings.mail.update'), [
                'host' => 'smtp2.example.com',
                'port' => 465,
                'username' => 'mailer',
                'password' => 'second-secret',
                'encryption' => 'ssl',
                'from_address' => 'noreply@example.com',
                'from_name' => 'CorePanel',
            ])
            ->assertRedirect(route('admin.settings.mail'));

        $this->assertSame('smtp2.example.com', $settings->getString('mail.host'));
        $this->assertSame('second-secret', $settings->getEncrypted('mail.password'));

        $this->actingAs($admin)
            ->get(route('admin.settings.mail'))
            ->assertOk()
            ->assertDontSee('second-secret', false)
            ->assertSee(__('Leave blank to keep the current password.'), false);
    }

    public function test_client_disables_ticket_mail_via_ui_and_staff_reply_is_skipped(): void
    {
        Notification::fake();

        [$owner, $client] = $this->makeClientUser();
        $staff = User::factory()->withRole('support')->create();

        $this->actingAs($owner)
            ->put(route('client.settings.notifications.update'), [
                'channels' => [
                    'mail' => '1',
                    'database' => '1',
                ],
                'categories' => [
                    'billing' => ['mail' => '1', 'database' => '1'],
                    'tickets' => ['database' => '1'],
                    'services' => ['mail' => '1', 'database' => '1'],
                ],
            ])
            ->assertRedirect(route('client.settings.notifications'));

        $ticket = Ticket::factory()->create([
            'client_id' => $client->id,
            'subject' => 'Opt-out acceptance ticket',
        ]);
        $message = $ticket->messages()->create([
            'user_id' => $staff->id,
            'message' => 'We are investigating.',
            'created_at' => now(),
        ]);

        app(NotifyParticipantsOnTicketReplied::class)->handle(
            new TicketReplied($ticket, $message, $staff),
        );

        Notification::assertNotSentTo($owner, TicketReplyNotification::class);
    }

    public function test_client_disables_billing_mail_via_ui_and_invoice_reminder_is_skipped(): void
    {
        Notification::fake();

        $owner = User::factory()->withRole('client')->create([
            'email' => 'billing-optout@example.test',
        ]);
        Client::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->put(route('client.settings.notifications.update'), [
                'channels' => [
                    'mail' => '1',
                    'database' => '1',
                ],
                'categories' => [
                    'billing' => ['database' => '1'],
                    'tickets' => ['mail' => '1', 'database' => '1'],
                    'services' => ['mail' => '1', 'database' => '1'],
                ],
            ])
            ->assertRedirect(route('client.settings.notifications'));

        $invoice = Invoice::factory()->unpaid()->create([
            'total_amount' => '100.00',
            'subtotal' => '100.00',
            'due_at' => Carbon::parse('2026-01-20'),
            'contact_email' => 'billing-optout@example.test',
            'reminder_level' => 0,
        ]);

        $result = app(InvoiceReminderService::class)->process(Carbon::parse('2026-01-13'));

        $this->assertSame(0, $result->sent);
        $this->assertGreaterThanOrEqual(1, $result->skipped);
        $this->assertSame(0, $invoice->fresh()->reminder_level);
        Notification::assertNothingSent();
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function makeClientUser(): array
    {
        $owner = User::factory()->withRole('client')->create();
        $client = Client::factory()->create(['user_id' => $owner->id]);
        $client->users()->attach($owner->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);

        return [$owner, $client];
    }
}

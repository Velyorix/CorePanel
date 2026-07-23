<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Core\Auth\Notifications\ResetPasswordNotification;
use Core\Auth\Notifications\VerifyEmailNotification;
use Core\Billing\Enums\InvoiceReminderLevel;
use Core\Billing\Models\Invoice;
use Core\Billing\Notifications\InvoiceReminderNotification;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Clients\Models\ClientUserInvitation;
use Core\Clients\Notifications\ClientUserInvitationNotification;
use Core\Notifications\Notifications\ChannelNotification;
use Core\Provisioning\Notifications\ServiceProvisionedNotification;
use Core\Provisioning\Notifications\ServiceProvisioningFailedNotification;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Services\Notifications\ServiceStatusNotification;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Models\TicketMessage;
use Core\Tickets\Notifications\TicketOpenedNotification;
use Core\Tickets\Notifications\TicketReplyNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config(['corepanel.name' => 'CorePanel Test']);
    }

    public function test_auth_reset_password_template_renders(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.test']);
        $html = (new ResetPasswordNotification('test-token'))->toMail($user)->render();

        $this->assertStringContainsString('Reset your password', $html);
        $this->assertStringContainsString('CorePanel Test', $html);
        $this->assertStringContainsString('reset-password/test-token', $html);
    }

    public function test_auth_verify_email_template_renders(): void
    {
        $user = User::factory()->create(['email' => 'verify@example.test']);
        $html = (new VerifyEmailNotification)->toMail($user)->render();

        $this->assertStringContainsString('Verify your email address', $html);
        $this->assertStringContainsString('/email/verify/', $html);
    }

    public function test_invoice_reminder_template_renders_level_content(): void
    {
        $invoice = Invoice::factory()->unpaid()->create([
            'invoice_number' => 'INV-2026-00042',
            'total_amount' => '120.00',
            'subtotal' => '120.00',
        ]);
        $user = User::factory()->create();

        $html = (new InvoiceReminderNotification($invoice, InvoiceReminderLevel::Overdue))
            ->toMail($user)
            ->render();

        $this->assertStringContainsString('INV-2026-00042', $html);
        $this->assertStringContainsString('overdue', strtolower(strip_tags($html)));
    }

    public function test_client_invitation_template_renders(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->create([
            'user_id' => $owner->id,
            'company_name' => 'Acme Hosting',
        ]);
        $invitation = ClientUserInvitation::query()->create([
            'client_id' => $client->id,
            'email' => 'guest@example.test',
            'token' => hash('sha256', 'plain-token'),
            'role' => ClientMembershipRole::User,
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
        ]);

        $html = (new ClientUserInvitationNotification($invitation, 'plain-token'))
            ->toMail($owner)
            ->render();

        $this->assertStringContainsString('Acme Hosting', $html);
        $this->assertStringContainsString('Accept invitation', $html);
    }

    public function test_ticket_opened_and_reply_templates_render(): void
    {
        $client = Client::factory()->create(['company_name' => 'Ticket Client']);
        $author = User::factory()->create(['name' => 'Support Agent']);
        $ticket = Ticket::factory()->numbered('TK-2026-00099')->create([
            'client_id' => $client->id,
            'subject' => 'DNS broken',
        ]);
        $message = TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'message' => 'We are checking nameservers now.',
        ]);

        $opened = (new TicketOpenedNotification($ticket))->toMail($author)->render();
        $this->assertStringContainsString('TK-2026-00099', $opened);
        $this->assertStringContainsString('DNS broken', $opened);
        $this->assertStringContainsString('Ticket Client', $opened);
        $this->assertStringContainsString('/admin/tickets/', $opened);

        $replyStaff = (new TicketReplyNotification($ticket, $message, $author, forStaff: true))
            ->toMail($author)
            ->render();
        $this->assertStringContainsString('Support Agent', $replyStaff);
        $this->assertStringContainsString('We are checking nameservers now.', $replyStaff);
        $this->assertStringContainsString('/admin/tickets/', $replyStaff);

        $replyClient = (new TicketReplyNotification($ticket, $message, $author, forStaff: false))
            ->toMail($author)
            ->render();
        $this->assertStringContainsString('/client/tickets/', $replyClient);
    }

    public function test_channel_notification_template_renders_action(): void
    {
        $user = User::factory()->create();
        $html = ChannelNotification::make(
            title: 'Channel title',
            message: 'Channel body message',
            channels: ['mail'],
            actionUrl: 'https://example.test/details',
            actionLabel: 'Open details',
        )->toMail($user)->render();

        $this->assertStringContainsString('Channel title', $html);
        $this->assertStringContainsString('Channel body message', $html);
        $this->assertStringContainsString('Open details', $html);
        $this->assertStringContainsString('https://example.test/details', $html);
    }

    public function test_service_and_provisioning_templates_render(): void
    {
        $user = User::factory()->create();
        $service = Service::factory()->active()->create([
            'hostname' => 'web-01.example.test',
        ]);

        $statusHtml = (new ServiceStatusNotification($service, ServiceStatus::Suspended))
            ->toMail($user)
            ->render();
        $this->assertStringContainsString('web-01.example.test', $statusHtml);
        $this->assertStringContainsString('suspended', strtolower(strip_tags($statusHtml)));

        $provisionedHtml = (new ServiceProvisionedNotification($service, 'ext-123'))
            ->toMail($user)
            ->render();
        $this->assertStringContainsString('web-01.example.test', $provisionedHtml);
        $this->assertStringContainsString('ext-123', $provisionedHtml);
        $this->assertStringContainsString('provisioned', strtolower(strip_tags($provisionedHtml)));

        $failedHtml = (new ServiceProvisioningFailedNotification($service, 'Provider timeout'))
            ->toMail($user)
            ->render();
        $this->assertStringContainsString('Provider timeout', $failedHtml);
        $this->assertStringContainsString('failed', strtolower(strip_tags($failedHtml)));
    }
}

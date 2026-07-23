<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Core\Notifications\Notifications\ChannelNotification;
use Core\Notifications\Notifications\QueuedChannelNotification;
use Core\Notifications\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notifications;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notifications = app(NotificationService::class);

        config([
            'corepanel.notifications.enabled' => true,
            'corepanel.notifications.default_channels' => ['mail', 'database'],
            'corepanel.notifications.queue_by_default' => false,
        ]);
    }

    public function test_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(NotificationService::class),
            app(NotificationService::class),
        );
    }

    public function test_send_writes_database_notification(): void
    {
        $user = User::factory()->create();

        $this->notifications->send(
            $user,
            ChannelNotification::make(
                title: 'Welcome',
                message: 'Your account is ready.',
                meta: ['source' => 'test'],
                channels: ['database'],
            ),
        );

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'type' => ChannelNotification::class,
        ]);

        $row = $user->notifications()->first();
        $this->assertNotNull($row);
        $this->assertSame('Welcome', $row->data['title'] ?? null);
        $this->assertSame('Your account is ready.', $row->data['message'] ?? null);
        $this->assertSame('test', $row->data['meta']['source'] ?? null);
        $this->assertNull($row->read_at);
    }

    public function test_send_dispatches_mail_channel(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->notifications->send(
            $user,
            ChannelNotification::make('Mail only', 'Hello by mail.', channels: ['mail']),
            channels: ['mail'],
        );

        Notification::assertSentTo(
            $user,
            ChannelNotification::class,
            function (ChannelNotification $notification, array $channels): bool {
                return $notification->title === 'Mail only'
                    && $channels === ['mail'];
            },
        );
    }

    public function test_send_supports_mail_and_database(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->notifications->send(
            $user,
            ChannelNotification::make(
                'Multi channel',
                'Delivered on mail and database.',
                channels: ['mail', 'database'],
            ),
        );

        Notification::assertSentTo(
            $user,
            ChannelNotification::class,
            function (ChannelNotification $notification, array $channels): bool {
                return $notification->title === 'Multi channel'
                    && $channels === ['mail', 'database'];
            },
        );

        // Fake intercepts delivery; verify payload shape without relying on DB writes.
        Notification::assertSentToTimes($user, ChannelNotification::class, 1);
    }

    public function test_send_respects_global_enabled_flag(): void
    {
        Notification::fake();
        config(['corepanel.notifications.enabled' => false]);

        $user = User::factory()->create();

        $this->notifications->send(
            $user,
            ChannelNotification::make('Silent', 'Should not send.', channels: ['mail', 'database']),
        );

        Notification::assertNothingSent();
        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_queue_requires_should_queue_notification(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ShouldQueue');

        $this->notifications->queue(
            $user,
            ChannelNotification::make('Nope', 'Not queueable.'),
        );
    }

    public function test_queue_method_sends_queued_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $notification = new QueuedChannelNotification(
            title: 'Queued hello',
            message: 'Processed asynchronously.',
            channels: ['mail', 'database'],
        );

        $this->notifications->queue($user, $notification);

        Notification::assertSentTo(
            $user,
            QueuedChannelNotification::class,
            fn (QueuedChannelNotification $sent): bool => $sent->title === 'Queued hello',
        );
    }

    public function test_send_mail_on_demand_route(): void
    {
        Notification::fake();

        $this->notifications->sendMail(
            'guest@example.test',
            ChannelNotification::make('Invite', 'Join us.', channels: ['mail']),
        );

        Notification::assertSentOnDemand(
            ChannelNotification::class,
            function (ChannelNotification $notification, array $channels, object $notifiable): bool {
                return ($notifiable->routes['mail'] ?? null) === 'guest@example.test'
                    && $notification->title === 'Invite'
                    && $channels === ['mail'];
            },
        );
    }

    public function test_default_channels_come_from_config(): void
    {
        config(['corepanel.notifications.default_channels' => ['database']]);

        $this->assertSame(['database'], $this->notifications->defaultChannels());

        $user = User::factory()->create();

        $this->notifications->send(
            $user,
            ChannelNotification::make('Defaults', 'Uses config channels.'),
        );

        $this->assertSame(1, $user->notifications()->count());
    }
}

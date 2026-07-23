<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Core\Notifications\Notifications\ChannelNotification;
use Core\Notifications\Services\NotificationPreferenceService;
use Core\Notifications\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationPreferenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationPreferenceService $preferences;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preferences = app(NotificationPreferenceService::class);

        config([
            'corepanel.notifications.enabled' => true,
            'corepanel.notifications.default_channels' => ['mail', 'database'],
        ]);
    }

    public function test_defaults_enable_all_channels_and_categories(): void
    {
        $user = User::factory()->create();
        $prefs = $this->preferences->forUser($user);

        $this->assertTrue($prefs['channels']['mail']);
        $this->assertTrue($prefs['channels']['database']);
        $this->assertTrue($prefs['categories']['tickets']['mail']);
        $this->assertTrue($prefs['categories']['billing']['database']);
    }

    public function test_opt_out_channel_filters_delivery(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->preferences->update($user, [
            'channels' => ['mail' => false, 'database' => true],
            'categories' => [
                'billing' => ['mail' => true, 'database' => true],
                'tickets' => ['mail' => true, 'database' => true],
                'services' => ['mail' => true, 'database' => true],
            ],
        ]);

        app(NotificationService::class)->send(
            $user,
            ChannelNotification::make('Hello', 'World', category: 'tickets'),
        );

        Notification::assertSentTo(
            $user,
            ChannelNotification::class,
            function (ChannelNotification $notification, array $channels) use ($user): bool {
                return $channels === ['database']
                    && $notification->via($user) === ['database'];
            },
        );
    }

    public function test_category_opt_out_blocks_mail_for_topic(): void
    {
        $user = User::factory()->create();
        $this->preferences->update($user, [
            'channels' => ['mail' => true, 'database' => true],
            'categories' => [
                'billing' => ['mail' => true, 'database' => true],
                'tickets' => ['mail' => false, 'database' => true],
                'services' => ['mail' => true, 'database' => true],
            ],
        ]);

        $this->assertFalse($this->preferences->allows($user, 'mail', 'tickets'));
        $this->assertTrue($this->preferences->allows($user, 'database', 'tickets'));
        $this->assertTrue($this->preferences->allows($user, 'mail', 'billing'));
        $this->assertSame(
            ['database'],
            $this->preferences->filterChannels($user, ['mail', 'database'], 'tickets'),
        );
    }

    public function test_anonymous_notifiable_is_not_filtered(): void
    {
        $route = Notification::route('mail', 'billing@example.com');

        $this->assertTrue($this->preferences->allows($route, 'mail', 'billing'));
        $this->assertSame(
            ['mail'],
            $this->preferences->filterChannels($route, ['mail'], 'billing'),
        );
    }
}

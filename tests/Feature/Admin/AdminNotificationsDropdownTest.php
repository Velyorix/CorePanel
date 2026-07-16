<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Core\Admin\Notifications\AdminNotificationFeed;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationsDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-notifications',
        ]);
    }

    public function test_admin_topbar_renders_notifications_dropdown_placeholder(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="'.__('Notifications').'"', false)
            ->assertSee(__('Placeholder feed until the notification center is connected.'), false)
            ->assertSee(__('High CPU usage detected'), false)
            ->assertSee(__('Payment webhook failed'), false)
            ->assertSee(__('New support ticket'), false)
            ->assertSee(__('View all notifications'), false)
            ->assertSee(__('Coming soon'), false)
            ->assertSee('data-notification="node_cpu"', false)
            ->assertSee('data-notification="payment_webhook"', false)
            ->assertSee('data-notification="support_ticket"', false);
    }

    public function test_notifications_dropdown_shows_unread_count_badge(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $unreadCount = app(AdminNotificationFeed::class)->unreadCount();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-unread-count="'.$unreadCount.'"', false);
    }

    public function test_support_user_sees_notifications_dropdown_on_admin_pages(): void
    {
        $support = User::factory()->withRole('support')->create();

        $this->actingAs($support)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="'.__('Notifications').'"', false);
    }

    public function test_notifications_dropdown_is_available_on_profile_page_topbar(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee('aria-label="'.__('Notifications').'"', false);
    }

    public function test_notification_feed_exposes_placeholder_items_and_unread_count(): void
    {
        $feed = app(AdminNotificationFeed::class);

        $this->assertCount(3, $feed->placeholders());
        $this->assertSame(2, $feed->unreadCount());
        $this->assertSame('node_cpu', $feed->placeholders()[0]['key']);
    }
}

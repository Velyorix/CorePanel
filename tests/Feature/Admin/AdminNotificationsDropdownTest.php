<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Core\Admin\Models\AdminNotification;
use Core\Admin\Notifications\AdminNotificationFeed;
use Core\Admin\Services\AdminNotificationService;
use Core\Sync\Services\SyncAlertService;
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

    public function test_admin_topbar_renders_notifications_dropdown_with_sync_alert(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        app(AdminNotificationService::class)->upsert(
            type: SyncAlertService::TYPE_SERVICE_DIVERGED,
            dedupeKey: 'sync.service.42',
            title: __('Service sync divergence'),
            message: __('Service #42 diverged from provider.'),
            variant: 'warning',
        );

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="'.__('Notifications').'"', false)
            ->assertSee(__('Recent system alerts from sync and infrastructure jobs.'), false)
            ->assertSee(__('Service sync divergence'), false)
            ->assertSee(__('Service #42 diverged from provider.'), false)
            ->assertSee(__('View sync history'), false)
            ->assertSee('data-notification="sync.service.42"', false);
    }

    public function test_notifications_dropdown_shows_unread_count_badge(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        app(AdminNotificationService::class)->upsert(
            type: SyncAlertService::TYPE_SERVICE_FAILED,
            dedupeKey: 'sync.service.7',
            title: __('Service sync failed'),
            message: __('Poll failed'),
            variant: 'danger',
        );

        app(AdminNotificationService::class)->upsert(
            type: SyncAlertService::TYPE_NODE_FAILED,
            dedupeKey: 'sync.node.3',
            title: __('Node sync failed'),
            message: __('Node sync failed'),
            variant: 'danger',
        );

        AdminNotification::query()
            ->where('dedupe_key', 'sync.node.3')
            ->update(['read_at' => now()]);

        $unreadCount = app(AdminNotificationFeed::class)->unreadCount();

        $this->assertSame(1, $unreadCount);

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

    public function test_notification_feed_exposes_recent_items_and_unread_count(): void
    {
        app(AdminNotificationService::class)->upsert(
            type: SyncAlertService::TYPE_SERVICE_DIVERGED,
            dedupeKey: 'sync.service.99',
            title: __('Service sync divergence'),
            message: __('Diverged service'),
            variant: 'warning',
        );

        $feed = app(AdminNotificationFeed::class);

        $this->assertCount(1, $feed->recent());
        $this->assertSame(1, $feed->unreadCount());
        $this->assertSame('sync.service.99', $feed->recent()[0]['key']);
    }

    public function test_notifications_dropdown_shows_empty_state_when_no_alerts(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('No alerts yet.'), false)
            ->assertSee(__('View sync history'), false);
    }
}

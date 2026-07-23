<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Core\Client\Navigation\ClientNavigation;
use Core\Notifications\Services\NotificationPreferenceService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientNotificationPreferencesTest extends TestCase
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
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-notif-prefs',
        ]);
    }

    public function test_client_can_view_and_update_notification_preferences(): void
    {
        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get(route('client.settings.notifications'))
            ->assertOk()
            ->assertSee(__('Channels'), false)
            ->assertSee(__('Topics'), false)
            ->assertSee(__('Save preferences'), false);

        $this->actingAs($client)
            ->from(route('client.settings.notifications'))
            ->put(route('client.settings.notifications.update'), [
                'channels' => [
                    'mail' => '1',
                    // database unchecked
                ],
                'categories' => [
                    'billing' => ['mail' => '1'],
                    'tickets' => ['database' => '1'],
                    'services' => [
                        'mail' => '1',
                        'database' => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('client.settings.notifications'))
            ->assertSessionHas('status');

        $client->refresh();
        $prefs = app(NotificationPreferenceService::class)->forUser($client);

        $this->assertTrue($prefs['channels']['mail']);
        $this->assertFalse($prefs['channels']['database']);
        $this->assertTrue($prefs['categories']['billing']['mail']);
        $this->assertFalse($prefs['categories']['billing']['database']);
        $this->assertFalse($prefs['categories']['tickets']['mail']);
        $this->assertTrue($prefs['categories']['tickets']['database']);
    }

    public function test_guest_and_admin_cannot_access_client_notification_preferences(): void
    {
        $this->get(route('client.settings.notifications'))
            ->assertRedirect();

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('client.settings.notifications'))
            ->assertForbidden();
    }

    public function test_navigation_links_notifications_settings(): void
    {
        $client = User::factory()->withRole('client')->create();

        $item = collect(app(ClientNavigation::class)->forUser($client))
            ->flatMap(fn (array $section) => $section['items'])
            ->firstWhere('label', __('Notifications'));

        $this->assertNotNull($item);
        $this->assertFalse($item['placeholder']);
        $this->assertSame(route('client.settings.notifications'), $item['url']);
    }
}

<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Core\Orders\Models\OrderItem;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-orders',
        ]);
    }

    public function test_client_can_view_own_orders_index_and_detail(): void
    {
        [$user, $client] = $this->makeClientUser();
        $order = Order::factory()->pendingPayment()->forClient($client)->create([
            'order_number' => 'ORD-CLIENT-0001',
            'total_amount' => '42.50',
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'VPS Starter',
            'line_total' => '42.50',
        ]);

        $this->actingAs($user)
            ->get(route('client.orders.index'))
            ->assertOk()
            ->assertSee('ORD-CLIENT-0001')
            ->assertSee(__('Pending payment'));

        $this->actingAs($user)
            ->get(route('client.orders.show', $order))
            ->assertOk()
            ->assertSee('ORD-CLIENT-0001')
            ->assertSee('VPS Starter')
            ->assertSee(__('Order placed'))
            ->assertSee(__('Payment received'))
            ->assertSee(__('In progress'));
    }

    public function test_client_sees_updated_status_after_payment(): void
    {
        [$user, $client] = $this->makeClientUser();
        $order = Order::factory()->paid()->forClient($client)->create([
            'order_number' => 'ORD-PAID-0001',
        ]);

        $this->actingAs($user)
            ->get(route('client.orders.show', $order))
            ->assertOk()
            ->assertSee(__('Paid'))
            ->assertSee(__('Payment received'))
            ->assertDontSee(__('In progress'));
    }

    public function test_client_orders_index_hides_drafts_and_filters_by_status(): void
    {
        [$user, $client] = $this->makeClientUser();
        Order::factory()->forClient($client)->create([
            'status' => OrderStatus::Draft,
            'order_number' => null,
        ]);
        Order::factory()->pendingPayment()->forClient($client)->create([
            'order_number' => 'ORD-PENDING-ONLY',
        ]);
        Order::factory()->paid()->forClient($client)->create([
            'order_number' => 'ORD-PAID-HIDDEN',
        ]);

        $this->actingAs($user)
            ->get(route('client.orders.index'))
            ->assertOk()
            ->assertSee('ORD-PENDING-ONLY')
            ->assertSee('ORD-PAID-HIDDEN')
            ->assertDontSee(__('Draft'));

        $this->actingAs($user)
            ->get(route('client.orders.index', [
                'status' => OrderStatus::PendingPayment->value,
            ]))
            ->assertOk()
            ->assertSee('ORD-PENDING-ONLY')
            ->assertDontSee('ORD-PAID-HIDDEN');
    }

    public function test_client_cannot_view_another_clients_order(): void
    {
        [$user] = $this->makeClientUser();
        $other = Client::factory()->create();
        $order = Order::factory()->pendingPayment()->forClient($other)->create();

        $this->actingAs($user)
            ->get(route('client.orders.show', $order))
            ->assertNotFound();
    }

    public function test_client_cannot_view_draft_order_detail(): void
    {
        [$user, $client] = $this->makeClientUser();
        $order = Order::factory()->forClient($client)->create([
            'status' => OrderStatus::Draft,
        ]);

        $this->actingAs($user)
            ->get(route('client.orders.show', $order))
            ->assertNotFound();
    }

    public function test_empty_orders_index_shows_empty_state(): void
    {
        [$user] = $this->makeClientUser();

        $this->actingAs($user)
            ->get(route('client.orders.index'))
            ->assertOk()
            ->assertSee(__('No orders yet'));
    }

    public function test_guest_is_redirected_from_client_orders(): void
    {
        $this->get(route('client.orders.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_without_client_access_cannot_view_orders(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('client.orders.index'))
            ->assertForbidden();
    }

    public function test_orders_nav_is_active_on_orders_page(): void
    {
        [$user] = $this->makeClientUser();

        $this->actingAs($user)
            ->get(route('client.orders.index'))
            ->assertOk()
            ->assertSee(__('Orders'), false)
            ->assertSee(route('client.orders.index'), false);
    }

    /**
     * @param  array<string, mixed>  $clientAttributes
     * @return array{0: User, 1: Client}
     */
    private function makeClientUser(array $clientAttributes = []): array
    {
        $user = User::factory()->withRole('client')->create();
        $client = Client::factory()->create([
            'user_id' => $user->id,
            ...$clientAttributes,
        ]);
        $client->users()->attach($user->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);

        return [$user, $client];
    }
}

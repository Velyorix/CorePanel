<?php

namespace Tests\Feature\Orders;

use App\Models\User;
use Core\Clients\Models\Client;
use Core\Orders\Enums\OrderSource;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Core\Orders\Models\OrderItem;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-orders',
        ]);
    }

    public function test_admin_can_view_orders_index_and_show(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create(['company_name' => 'Acme Hosting']);
        $order = Order::factory()->pendingPayment()->forClient($client)->create([
            'order_number' => 'ORD-20260717-000042',
            'contact_email' => 'billing@acme.test',
            'total_amount' => '49.99',
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'VPS Starter',
            'line_total' => '49.99',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('ORD-20260717-000042')
            ->assertSee('Acme Hosting');

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('ORD-20260717-000042')
            ->assertSee('VPS Starter')
            ->assertSee('billing@acme.test');
    }

    public function test_support_can_view_but_cannot_manage_order_status(): void
    {
        $support = User::factory()->withRole('support')->create();
        $order = Order::factory()->pendingPayment()->create();

        $this->actingAs($support)
            ->get(route('admin.orders.index'))
            ->assertOk();

        $this->actingAs($support)
            ->get(route('admin.orders.show', $order))
            ->assertOk();

        $this->actingAs($support)
            ->post(route('admin.orders.mark-paid', $order))
            ->assertForbidden();

        $this->actingAs($support)
            ->post(route('admin.orders.cancel', $order))
            ->assertForbidden();
    }

    public function test_client_role_cannot_access_admin_orders(): void
    {
        $clientUser = User::factory()->withRole('client')->create();

        $this->actingAs($clientUser)
            ->get(route('admin.orders.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_admin_orders(): void
    {
        $this->get(route('admin.orders.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_mark_pending_payment_paid_and_cancel(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $draft = Order::factory()->create([
            'status' => OrderStatus::Draft,
            'source' => OrderSource::Admin,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.orders.mark-pending-payment', $draft))
            ->assertRedirect(route('admin.orders.show', $draft))
            ->assertSessionHas('status');

        $draft->refresh();
        $this->assertSame(OrderStatus::PendingPayment, $draft->status);
        $this->assertNotNull($draft->order_number);
        $this->assertNotNull($draft->placed_at);

        $this->actingAs($admin)
            ->post(route('admin.orders.mark-paid', $draft))
            ->assertRedirect(route('admin.orders.show', $draft));

        $draft->refresh();
        $this->assertSame(OrderStatus::Paid, $draft->status);
        $this->assertNotNull($draft->paid_at);

        $pending = Order::factory()->pendingPayment()->create();

        $this->actingAs($admin)
            ->post(route('admin.orders.cancel', $pending), [
                'reason' => 'Customer requested cancellation',
            ])
            ->assertRedirect(route('admin.orders.show', $pending));

        $pending->refresh();
        $this->assertSame(OrderStatus::Cancelled, $pending->status);
        $this->assertNotNull($pending->cancelled_at);
        $this->assertStringContainsString('Customer requested cancellation', (string) $pending->notes);
    }

    public function test_admin_cannot_mark_paid_from_draft(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $draft = Order::factory()->create(['status' => OrderStatus::Draft]);

        $this->actingAs($admin)
            ->post(route('admin.orders.mark-paid', $draft))
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Draft, $draft->fresh()->status);
    }

    public function test_orders_index_filters_by_status_and_search(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $match = Order::factory()->pendingPayment()->create([
            'order_number' => 'ORD-FILTER-MATCH',
            'contact_name' => 'Filter Match',
        ]);
        Order::factory()->paid()->create([
            'order_number' => 'ORD-FILTER-OTHER',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.index', [
                'status' => OrderStatus::PendingPayment->value,
                'q' => 'FILTER-MATCH',
            ]))
            ->assertOk()
            ->assertSee('ORD-FILTER-MATCH')
            ->assertDontSee('ORD-FILTER-OTHER');

        $this->assertSame($match->id, Order::query()->where('order_number', 'ORD-FILTER-MATCH')->value('id'));
    }
}

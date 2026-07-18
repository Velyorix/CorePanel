<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Orders\Enums\CartStatus;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Cart;
use Core\Orders\Models\CartItem;
use Core\Orders\Models\Order;
use Core\Orders\Services\CheckoutDraftService;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductOptionType;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductAddon;
use Core\Products\Models\ProductCategory;
use Core\Products\Models\ProductOption;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end client order flow.
 * Narrower client Feature tests cover individual slices.
 */
class ClientOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-order-flow',
            'session.driver' => 'array',
            'corepanel.billing.tax_preview_rate' => 0.20,
            'corepanel.billing.tax_preview_label' => 'VAT (estimate)',
            'corepanel.checkout.coupon_enabled' => true,
        ]);
    }

    public function test_client_completes_full_order_flow_from_catalog_with_options_and_addons(): void
    {
        [$user, $client] = $this->makeClientUser();
        $product = $this->makeFlowProduct();

        $this->actingAs($user)
            ->get(route('client.catalog.index'))
            ->assertOk()
            ->assertSee($product->category->name);

        $this->actingAs($user)
            ->get(route('client.catalog.category', $product->category->slug))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('19.99');

        $this->actingAs($user)
            ->get(route('client.catalog.products.show', $product->slug))
            ->assertOk()
            ->assertSee(route('client.catalog.products.configure', $product->slug), false);

        $this->actingAs($user)
            ->get(route('client.catalog.products.configure', $product->slug))
            ->assertOk()
            ->assertSee(__('Hostname'))
            ->assertSee('RAM Size')
            ->assertSee('Daily Backup');

        $this->actingAs($user)
            ->post(route('client.catalog.products.configure.store', $product->slug), [
                'billing_cycle' => BillingCycle::Monthly->value,
                'quantity' => 1,
                'options' => [
                    'hostname' => 'node-01.example.test',
                    'ram_size' => '8gb',
                ],
                'addons' => ['backup'],
            ])
            ->assertRedirect(route('client.cart.index'))
            ->assertSessionHas('status');

        $cartItem = CartItem::query()->firstOrFail();
        $this->assertSame('32.99', $cartItem->unit_price);
        $this->assertSame('6.00', $cartItem->setup_fee);
        $this->assertSame([
            'hostname' => 'node-01.example.test',
            'ram_size' => '8gb',
        ], $cartItem->options);
        $this->assertSame(['backup'], $cartItem->addons);

        $this->actingAs($user)
            ->get(route('client.cart.index'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('32.99')
            ->assertSee('6.00')
            ->assertSee('38.99')
            ->assertSee('7.80')
            ->assertSee('46.79')
            ->assertSee(route('client.checkout.index'), false);

        $this->actingAs($user)
            ->get(route('client.checkout.index'))
            ->assertOk()
            ->assertSee(__('Billing details'))
            ->assertSee('38.99')
            ->assertSee('46.79');

        $this->actingAs($user)
            ->post(route('client.checkout.store'), [
                'contact_name' => 'Alice Client',
                'contact_email' => $user->email,
                'company_name' => 'Acme Hosting',
                'address' => '12 Rue de la Paix',
                'city' => 'Paris',
                'postal_code' => '75002',
                'country' => 'FR',
                'phone' => '+33123456789',
                'payment_method' => 'manual_transfer',
                'coupon_code' => 'WELCOME10',
            ])
            ->assertRedirect(route('client.checkout.complete'))
            ->assertSessionHas(CheckoutDraftService::SESSION_KEY);

        $this->actingAs($user)
            ->get(route('client.checkout.complete'))
            ->assertOk()
            ->assertSee(__('Place order'));

        $response = $this->actingAs($user)
            ->post(route('client.checkout.place'));

        $order = Order::query()->firstOrFail();

        $response
            ->assertRedirect(route('client.checkout.placed', $order))
            ->assertSessionHas('status')
            ->assertSessionMissing(CheckoutDraftService::SESSION_KEY);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame($client->id, $order->client_id);
        $this->assertSame('manual_transfer', $order->payment_method);
        $this->assertSame('WELCOME10', $order->coupon_code);
        $this->assertSame('32.99', $order->subtotal_recurring);
        $this->assertSame('6.00', $order->subtotal_setup);
        $this->assertSame('7.80', $order->tax_amount);
        $this->assertSame('46.79', $order->total_amount);
        $this->assertNotNull($order->placed_at);

        $this->assertCount(1, $order->items);
        $orderItem = $order->items->first();
        $this->assertSame($product->id, $orderItem->product_id);
        $this->assertSame('32.99', $orderItem->unit_price);
        $this->assertSame('6.00', $orderItem->setup_fee);
        $this->assertSame('38.99', $orderItem->line_total);
        $this->assertSame([
            'hostname' => 'node-01.example.test',
            'ram_size' => '8gb',
        ], $orderItem->options);
        $this->assertSame(['backup'], $orderItem->addons);

        $cart = Cart::query()->findOrFail($order->cart_id);
        $this->assertTrue($cart->status === CartStatus::Converted);

        $this->actingAs($user)
            ->get(route('client.checkout.placed', $order))
            ->assertOk()
            ->assertSee(__('Pending payment'))
            ->assertSee($product->name)
            ->assertSee('46.79');
    }

    public function test_configured_prices_flow_from_preview_through_cart_to_pending_order(): void
    {
        [$user, $client] = $this->makeClientUser();
        $product = $this->makeFlowProduct();

        $preview = $this->actingAs($user)
            ->postJson(route('client.catalog.products.configure.preview', $product->slug), [
                'billing_cycle' => BillingCycle::Monthly->value,
                'quantity' => 1,
                'options' => [
                    'hostname' => 'price-flow.example.test',
                    'ram_size' => '8gb',
                ],
                'addons' => ['backup'],
            ])
            ->assertOk()
            ->json();

        $this->assertSame('32.99', $preview['unit_price']);
        $this->assertSame('6.00', $preview['setup_fee']);
        $this->assertSame('32.99', $preview['recurring_subtotal']);
        $this->assertSame('38.99', $preview['first_payment_subtotal']);
        $this->assertSame('7.80', $preview['first_payment_tax']);
        $this->assertSame('46.79', $preview['first_payment_total']);

        $this->actingAs($user)
            ->post(route('client.catalog.products.configure.store', $product->slug), [
                'billing_cycle' => BillingCycle::Monthly->value,
                'quantity' => 1,
                'options' => [
                    'hostname' => 'price-flow.example.test',
                    'ram_size' => '8gb',
                ],
                'addons' => ['backup'],
            ])
            ->assertRedirect(route('client.cart.index'));

        $this->actingAs($user)
            ->get(route('client.cart.index'))
            ->assertOk()
            ->assertSee($preview['unit_price'])
            ->assertSee($preview['setup_fee'])
            ->assertSee($preview['first_payment_subtotal'])
            ->assertSee($preview['first_payment_tax'])
            ->assertSee($preview['first_payment_total']);

        $this->actingAs($user)
            ->post(route('client.checkout.store'), [
                'contact_name' => 'Price Flow',
                'contact_email' => $user->email,
                'address' => '1 Test Street',
                'city' => 'Lyon',
                'postal_code' => '69001',
                'country' => 'FR',
                'payment_method' => 'manual_transfer',
            ])
            ->assertRedirect(route('client.checkout.complete'));

        $this->actingAs($user)
            ->post(route('client.checkout.place'))
            ->assertRedirect();

        $order = Order::query()->where('client_id', $client->id)->firstOrFail();

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame($preview['recurring_subtotal'], $order->subtotal_recurring);
        $this->assertSame($preview['setup_fee'], $order->subtotal_setup);
        $this->assertSame($preview['first_payment_tax'], $order->tax_amount);
        $this->assertSame($preview['first_payment_total'], $order->total_amount);
    }

    public function test_catalog_browse_links_lead_to_configurator(): void
    {
        $user = User::factory()->withRole('client')->create();
        $product = $this->makeFlowProduct();

        $this->actingAs($user)
            ->get(route('client.catalog.index'))
            ->assertOk()
            ->assertSee(route('client.catalog.category', $product->category->slug), false);

        $this->actingAs($user)
            ->get(route('client.catalog.category', $product->category->slug))
            ->assertOk()
            ->assertSee(route('client.catalog.products.show', $product->slug), false);

        $this->actingAs($user)
            ->get(route('client.catalog.products.show', $product->slug))
            ->assertOk()
            ->assertSee(route('client.catalog.products.configure', $product->slug), false);

        $this->actingAs($user)
            ->get(route('client.catalog.products.configure', $product->slug))
            ->assertOk()
            ->assertSee(__('Add to cart'));
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function makeClientUser(): array
    {
        $user = User::factory()->withRole('client')->create();
        $client = Client::factory()->create([
            'user_id' => $user->id,
            'company_name' => 'Flow Client',
            'address' => '1 Default Street',
            'city' => 'Paris',
            'postal_code' => '75001',
            'country' => 'FR',
        ]);
        $client->users()->attach($user->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);

        return [$user, $client];
    }

    private function makeFlowProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'name' => 'Cloud Servers',
            'slug' => 'flow-cloud-'.fake()->unique()->numerify('###'),
        ]);

        $product = Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            '19.99',
            '5.00',
        )->create([
            'category_id' => $category->id,
            'name' => 'Flow Configurable VPS',
            'slug' => 'flow-vps-'.fake()->unique()->numerify('###'),
            'type' => ProductType::Vps,
        ]);

        ProductOption::factory()->required()->create([
            'product_id' => $product->id,
            'key' => 'hostname',
            'name' => 'Hostname',
            'type' => ProductOptionType::Text,
            'config' => ['max_length' => 64, 'format' => 'hostname'],
            'sort_order' => 1,
        ]);

        ProductOption::factory()->required()->select([
            ['value' => '4gb', 'label' => '4 GB', 'price_delta' => 0],
            ['value' => '8gb', 'label' => '8 GB', 'price_delta' => 10],
        ])->create([
            'product_id' => $product->id,
            'key' => 'ram_size',
            'name' => 'RAM Size',
            'sort_order' => 2,
        ]);

        ProductAddon::factory()->create([
            'product_id' => $product->id,
            'key' => 'backup',
            'name' => 'Daily Backup',
            'price' => '3.00',
            'setup_fee' => '1.00',
            'billing_cycle' => BillingCycle::Monthly,
            'is_enabled' => true,
        ]);

        return $product->fresh(['options', 'addons', 'pricing', 'category']);
    }
}

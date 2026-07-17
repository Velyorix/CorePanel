<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Orders\DataTransferObjects\CartItemData;
use Core\Orders\Enums\CartStatus;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Core\Orders\Services\CartService;
use Core\Orders\Services\CheckoutDraftService;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-checkout',
            'session.driver' => 'array',
            'corepanel.billing.tax_preview_rate' => 0.20,
            'corepanel.checkout.coupon_enabled' => true,
        ]);
    }

    public function test_checkout_draft_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(CheckoutDraftService::class),
            app(CheckoutDraftService::class),
        );
    }

    public function test_empty_cart_redirects_to_cart_from_checkout(): void
    {
        $user = User::factory()->withRole('client')->create();

        $this->actingAs($user)
            ->get(route('client.checkout.index'))
            ->assertRedirect(route('client.cart.index'))
            ->assertSessionHasErrors('cart');
    }

    public function test_checkout_page_prefills_client_billing_and_shows_summary(): void
    {
        [$user, $client] = $this->makeClientUser([
            'company_name' => 'Acme Hosting',
            'address' => '12 Rue de la Paix',
            'city' => 'Paris',
            'postal_code' => '75002',
            'country' => 'FR',
            'phone' => '+33123456789',
        ]);
        $product = $this->makeProduct('checkout-vps', '19.99', '5.00');
        $this->addLine($client, $product, 1);

        $this->actingAs($user)
            ->get(route('client.checkout.index'))
            ->assertOk()
            ->assertSee(__('Billing details'))
            ->assertSee('Acme Hosting')
            ->assertSee('12 Rue de la Paix')
            ->assertSee(__('Payment method'))
            ->assertSee(__('Bank transfer'))
            ->assertSee(__('Credit card'))
            ->assertSee(__('Coupon'))
            ->assertSee('19.99')
            ->assertSee('5.00')
            ->assertSee(__('Save and continue'));
    }

    public function test_checkout_submit_stores_draft_updates_client_without_placing_order(): void
    {
        [$user, $client] = $this->makeClientUser([
            'address' => 'Old street',
            'city' => 'Lyon',
            'postal_code' => '69001',
            'country' => 'FR',
        ]);
        $product = $this->makeProduct('checkout-submit-vps');
        $this->addLine($client, $product, 1);

        $this->actingAs($user)
            ->post(route('client.checkout.store'), [
                'contact_name' => 'Alice Client',
                'contact_email' => $user->email,
                'company_name' => 'New Co',
                'vat_number' => 'FR123',
                'address' => '99 Avenue Test',
                'city' => 'Nantes',
                'postal_code' => '44000',
                'country' => 'fr',
                'phone' => '+33200000000',
                'payment_method' => 'manual_transfer',
                'coupon_code' => 'WELCOME10',
            ])
            ->assertRedirect(route('client.checkout.complete'))
            ->assertSessionHas('status')
            ->assertSessionHas(CheckoutDraftService::SESSION_KEY);

        $client->refresh();
        $this->assertSame('New Co', $client->company_name);
        $this->assertSame('99 Avenue Test', $client->address);
        $this->assertSame('Nantes', $client->city);
        $this->assertSame('44000', $client->postal_code);
        $this->assertSame('FR', $client->country);
        $this->assertSame('FR123', $client->vat_number);

        $draft = app(CheckoutDraftService::class)->load(session()->driver());
        $this->assertNotNull($draft);
        $this->assertSame('manual_transfer', $draft->paymentMethod);
        $this->assertSame('WELCOME10', $draft->couponCode);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_place_order_converts_cart_to_pending_payment_order(): void
    {
        [$user, $client] = $this->makeClientUser([
            'address' => '12 Rue Test',
            'city' => 'Paris',
            'postal_code' => '75001',
            'country' => 'FR',
        ]);
        $product = $this->makeProduct('place-order-vps', '19.99', '5.00');
        $this->addLine($client, $product, 1);

        $this->actingAs($user)
            ->post(route('client.checkout.store'), [
                'contact_name' => 'Alice Client',
                'contact_email' => $user->email,
                'company_name' => 'Acme',
                'address' => '12 Rue Test',
                'city' => 'Paris',
                'postal_code' => '75001',
                'country' => 'FR',
                'payment_method' => 'manual_transfer',
                'coupon_code' => 'WELCOME10',
            ])
            ->assertRedirect(route('client.checkout.complete'));

        $response = $this->actingAs($user)
            ->post(route('client.checkout.place'));

        $order = Order::query()->firstOrFail();

        $response
            ->assertRedirect(route('client.checkout.placed', $order))
            ->assertSessionHas('status')
            ->assertSessionMissing(CheckoutDraftService::SESSION_KEY);

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame($client->id, $order->client_id);
        $this->assertSame('manual_transfer', $order->payment_method);
        $this->assertSame('WELCOME10', $order->coupon_code);
        $this->assertSame('19.99', $order->subtotal_recurring);
        $this->assertSame('5.00', $order->subtotal_setup);
        $this->assertCount(1, $order->items);
        $this->assertTrue($order->cart?->status === CartStatus::Converted);

        $this->actingAs($user)
            ->get(route('client.checkout.placed', $order))
            ->assertOk()
            ->assertSee(__('Pending payment'))
            ->assertSee($product->name)
            ->assertSee(__('Order #:id', ['id' => $order->id]));
    }

    public function test_place_order_requires_saved_draft(): void
    {
        [$user, $client] = $this->makeClientUser();
        $this->addLine($client, $this->makeProduct('no-draft-vps'), 1);

        $this->actingAs($user)
            ->post(route('client.checkout.place'))
            ->assertRedirect(route('client.checkout.index'))
            ->assertSessionHasErrors('checkout');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_rejects_disabled_payment_method(): void
    {
        [$user, $client] = $this->makeClientUser();
        $this->addLine($client, $this->makeProduct('pay-method-vps'), 1);

        $this->actingAs($user)
            ->from(route('client.checkout.index'))
            ->post(route('client.checkout.store'), [
                'contact_name' => 'Alice Client',
                'contact_email' => $user->email,
                'address' => '1 Street',
                'city' => 'Paris',
                'postal_code' => '75001',
                'country' => 'FR',
                'payment_method' => 'card',
            ])
            ->assertRedirect(route('client.checkout.index'))
            ->assertSessionHasErrors('payment_method');
    }

    public function test_checkout_rejects_invalid_country(): void
    {
        [$user, $client] = $this->makeClientUser();
        $this->addLine($client, $this->makeProduct('country-vps'), 1);

        $this->actingAs($user)
            ->from(route('client.checkout.index'))
            ->post(route('client.checkout.store'), [
                'contact_name' => 'Alice Client',
                'contact_email' => $user->email,
                'address' => '1 Street',
                'city' => 'Paris',
                'postal_code' => '75001',
                'country' => 'FRA',
                'payment_method' => 'manual_transfer',
            ])
            ->assertRedirect(route('client.checkout.index'))
            ->assertSessionHasErrors('country');
    }

    public function test_apply_coupon_stores_code_without_changing_totals(): void
    {
        [$user, $client] = $this->makeClientUser();
        $product = $this->makeProduct('coupon-vps', '10.00', '0.00');
        $this->addLine($client, $product, 1);

        $this->actingAs($user)
            ->patch(route('client.checkout.coupon.apply'), [
                'coupon_code' => 'SAVE20',
            ])
            ->assertRedirect(route('client.checkout.index'))
            ->assertSessionHas('status');

        $draft = app(CheckoutDraftService::class)->load(session()->driver());
        $this->assertSame('SAVE20', $draft?->couponCode);

        $summary = app(\Core\Orders\Services\CartSummary::class)
            ->summarize(app(CartService::class)->getOrCreate($client, null));

        $this->assertSame('10.00', $summary['recurring_subtotal']);
        $this->assertSame('10.00', $summary['first_payment_subtotal']);
    }

    public function test_complete_page_requires_saved_draft(): void
    {
        $user = User::factory()->withRole('client')->create();

        $this->actingAs($user)
            ->get(route('client.checkout.complete'))
            ->assertRedirect(route('client.checkout.index'));
    }

    public function test_guest_is_redirected_from_checkout(): void
    {
        $this->get(route('client.checkout.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_without_client_access_cannot_checkout(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('client.checkout.index'))
            ->assertForbidden();
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

    private function makeProduct(
        string $slug,
        string $price = '10.00',
        string $setupFee = '0.00',
    ): Product {
        $category = ProductCategory::factory()->create([
            'slug' => 'checkout-cat-'.fake()->unique()->numerify('###'),
        ]);

        return Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            $price,
            $setupFee,
        )->create([
            'category_id' => $category->id,
            'name' => 'Checkout '.$slug,
            'slug' => $slug,
            'type' => ProductType::Vps,
            'status' => ProductStatus::Published,
        ]);
    }

    private function addLine(Client $client, Product $product, int $quantity): void
    {
        $cart = app(CartService::class)->getOrCreate($client, null);
        app(CartService::class)->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'quantity' => $quantity,
        ]));
    }
}

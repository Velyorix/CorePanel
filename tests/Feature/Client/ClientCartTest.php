<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Orders\DataTransferObjects\CartItemData;
use Core\Orders\Models\CartItem;
use Core\Orders\Services\CartService;
use Core\Orders\Services\CartSummary;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-cart',
            'session.driver' => 'array',
            'corepanel.billing.tax_preview_rate' => 0.20,
            'corepanel.billing.tax_preview_label' => 'VAT (estimate)',
        ]);
    }

    public function test_cart_summary_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(CartSummary::class),
            app(CartSummary::class),
        );
    }

    public function test_empty_cart_page_shows_continue_shopping(): void
    {
        $user = User::factory()->withRole('client')->create();

        $this->actingAs($user)
            ->get(route('client.cart.index'))
            ->assertOk()
            ->assertSee(__('Your cart is empty'))
            ->assertSee(__('Browse catalog'))
            ->assertSee(route('client.catalog.index'), false);
    }

    public function test_client_can_view_cart_with_summary_totals(): void
    {
        [$user, $client] = $this->makeClientUser();
        $product = $this->makeProduct('cart-summary-vps', '19.99', '5.00');

        $cart = app(CartService::class)->getOrCreate($client, null);
        app(CartService::class)->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'quantity' => 2,
        ]));

        $this->actingAs($user)
            ->get(route('client.cart.index'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee(__('Order summary'))
            ->assertSee('39.98')
            ->assertSee('5.00')
            ->assertSee('44.98')
            ->assertSee('9.00')
            ->assertSee('53.98')
            ->assertSee(__('VAT :rate%', ['rate' => '20']))
            ->assertSee(__('Proceed to checkout'))
            ->assertSee(route('client.checkout.index'), false)
            ->assertDontSee(__('Tax is an estimate; final VAT is calculated at checkout.'));

        $summary = app(CartSummary::class)->summarize(
            app(CartService::class)->getOrCreate($client, null)->fresh(['items.product', 'client']),
        );
        $this->assertSame('tax_rules', $summary['tax_engine']);
        $this->assertFalse($summary['tax_is_estimate']);
    }

    public function test_client_can_update_cart_item_quantity(): void
    {
        [$user, $client] = $this->makeClientUser();
        $product = $this->makeProduct('qty-vps');
        $item = $this->addLine($client, $product, 1);

        $this->actingAs($user)
            ->patch(route('client.cart.items.update', $item), [
                'quantity' => 3,
            ])
            ->assertRedirect(route('client.cart.index'))
            ->assertSessionHas('status');

        $this->assertSame(3, $item->fresh()->quantity);
    }

    public function test_client_can_remove_cart_item(): void
    {
        [$user, $client] = $this->makeClientUser();
        $product = $this->makeProduct('remove-vps');
        $item = $this->addLine($client, $product, 1);

        $this->actingAs($user)
            ->delete(route('client.cart.items.destroy', $item))
            ->assertRedirect(route('client.cart.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_client_can_clear_cart(): void
    {
        [$user, $client] = $this->makeClientUser();
        $product = $this->makeProduct('clear-vps');
        $this->addLine($client, $product, 2);

        $this->actingAs($user)
            ->delete(route('client.cart.clear'))
            ->assertRedirect(route('client.cart.index'))
            ->assertSessionHas('status');

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_cannot_update_another_clients_cart_item(): void
    {
        [$user] = $this->makeClientUser();
        [, $otherClient] = $this->makeClientUser('other-cart-user@example.test');
        $product = $this->makeProduct('foreign-vps');
        $foreignItem = $this->addLine($otherClient, $product, 1);

        $this->actingAs($user)
            ->from(route('client.cart.index'))
            ->patch(route('client.cart.items.update', $foreignItem), [
                'quantity' => 5,
            ])
            ->assertRedirect(route('client.cart.index'))
            ->assertSessionHasErrors('cart');

        $this->assertSame(1, $foreignItem->fresh()->quantity);
    }

    public function test_guest_is_redirected_from_cart(): void
    {
        $this->get(route('client.cart.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_without_client_access_cannot_view_cart(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('client.cart.index'))
            ->assertForbidden();
    }

    public function test_cart_nav_is_active_on_cart_page(): void
    {
        $user = User::factory()->withRole('client')->create();

        $this->actingAs($user)
            ->get(route('client.cart.index'))
            ->assertOk()
            ->assertSee(__('Cart'), false)
            ->assertSee('aria-current="page"', false);
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function makeClientUser(?string $email = null): array
    {
        $user = User::factory()->withRole('client')->create(
            $email !== null ? ['email' => $email] : [],
        );
        $client = Client::factory()->create([
            'user_id' => $user->id,
            'country' => 'FR',
            'vat_number' => null,
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
            'slug' => 'cart-cat-'.fake()->unique()->numerify('###'),
        ]);

        return Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            $price,
            $setupFee,
        )->create([
            'category_id' => $category->id,
            'name' => 'Cart '.$slug,
            'slug' => $slug,
            'type' => ProductType::Vps,
            'status' => ProductStatus::Published,
        ]);
    }

    private function addLine(Client $client, Product $product, int $quantity): CartItem
    {
        $cart = app(CartService::class)->getOrCreate($client, null);

        return app(CartService::class)->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'quantity' => $quantity,
        ]));
    }
}

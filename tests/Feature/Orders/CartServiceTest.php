<?php

namespace Tests\Feature\Orders;

use Core\Clients\Models\Client;
use Core\Orders\DataTransferObjects\CartItemData;
use Core\Orders\Enums\CartStatus;
use Core\Orders\Models\Cart;
use Core\Orders\Models\CartItem;
use Core\Orders\Services\CartService;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductStatus;
use Core\Products\Models\Product;
use Core\Products\Models\ProductAddon;
use Core\Products\Models\ProductPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = app(CartService::class);
    }

    public function test_cart_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(CartService::class),
            app(CartService::class),
        );
    }

    public function test_get_or_create_session_cart(): void
    {
        $cart = $this->cartService->getOrCreate(null, 'guest-session-1', 'EUR');

        $this->assertNull($cart->client_id);
        $this->assertSame('guest-session-1', $cart->session_id);
        $this->assertTrue($cart->status === CartStatus::Open);
        $this->assertSame('EUR', $cart->currency);

        $again = $this->cartService->getOrCreate(null, 'guest-session-1');

        $this->assertSame($cart->id, $again->id);
        $this->assertSame(1, Cart::query()->count());
    }

    public function test_get_or_create_client_cart(): void
    {
        $client = Client::factory()->create();

        $cart = $this->cartService->getOrCreate($client, 'ignored-for-client');

        $this->assertSame($client->id, $cart->client_id);
        $this->assertNull($cart->session_id);
        $this->assertTrue($cart->status === CartStatus::Open);

        $again = $this->cartService->getOrCreate($client, 'another-session');

        $this->assertSame($cart->id, $again->id);
    }

    public function test_get_or_create_requires_client_or_session(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A client or session_id is required');

        $this->cartService->getOrCreate(null, null);
    }

    public function test_add_item_snapshots_enabled_pricing_and_merges_identical_lines(): void
    {
        $product = Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            '19.99',
            '5.00',
        )->create(['slug' => 'cart-vps']);

        $cart = $this->cartService->getOrCreate(null, 'add-session');

        $item = $this->cartService->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'quantity' => 1,
        ]));

        $this->assertSame($cart->id, $item->cart_id);
        $this->assertSame('19.99', $item->unit_price);
        $this->assertSame('5.00', $item->setup_fee);
        $this->assertSame(1, $item->quantity);

        $merged = $this->cartService->addItem($cart->fresh(), CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'quantity' => 2,
        ]));

        $this->assertSame($item->id, $merged->id);
        $this->assertSame(3, $merged->quantity);
        $this->assertSame(1, CartItem::query()->where('cart_id', $cart->id)->count());
    }

    public function test_add_item_rejects_unpublished_product(): void
    {
        $product = Product::factory()->create([
            'status' => ProductStatus::Draft,
            'slug' => 'draft-product',
        ]);
        ProductPricing::factory()->create([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly,
            'price' => '10.00',
            'is_enabled' => true,
        ]);

        $cart = $this->cartService->getOrCreate(null, 'draft-session');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only published products');

        $this->cartService->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
        ]));
    }

    public function test_add_item_rejects_disabled_pricing(): void
    {
        $product = Product::factory()->published()->create(['slug' => 'disabled-price']);
        ProductPricing::factory()->disabled()->create([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly,
            'price' => '10.00',
        ]);

        $cart = $this->cartService->getOrCreate(null, 'disabled-session');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No enabled pricing found');

        $this->cartService->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
        ]));
    }

    public function test_add_item_validates_addons(): void
    {
        $product = Product::factory()->published()->withPricing()->create(['slug' => 'with-addons']);
        ProductAddon::factory()->create([
            'product_id' => $product->id,
            'key' => 'backup',
            'is_enabled' => true,
        ]);

        $cart = $this->cartService->getOrCreate(null, 'addon-session');

        $item = $this->cartService->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'addons' => ['backup'],
        ]));

        $this->assertSame(['backup'], $item->addons);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Addon [missing] is not available');

        $this->cartService->addItem($cart->fresh(), CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'addons' => ['missing'],
        ]));
    }

    public function test_update_quantity_remove_item_and_clear(): void
    {
        $product = Product::factory()->published()->withPricing()->create(['slug' => 'qty-product']);
        $cart = $this->cartService->getOrCreate(null, 'qty-session');

        $item = $this->cartService->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
        ]));

        $updated = $this->cartService->updateQuantity($cart, $item, 4);
        $this->assertSame(4, $updated->quantity);

        $this->cartService->removeItem($cart, $updated);
        $this->assertSame(0, $cart->items()->count());

        $item = $this->cartService->addItem($cart->fresh(), CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'quantity' => 2,
        ]));

        $this->cartService->clear($cart->fresh());
        $this->assertTrue($cart->fresh()->isEmpty());
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_cannot_modify_abandoned_cart(): void
    {
        $cart = Cart::factory()->abandoned()->forSession('abandoned-session')->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be modified');

        $this->cartService->clear($cart);
    }

    public function test_merge_session_cart_into_client(): void
    {
        $client = Client::factory()->create();
        $productA = Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            '10.00',
            '0',
        )->create(['slug' => 'merge-a']);
        $productB = Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            '20.00',
            '0',
        )->create(['slug' => 'merge-b']);

        $sessionCart = $this->cartService->getOrCreate(null, 'merge-session');
        $this->cartService->addItem($sessionCart, CartItemData::fromArray([
            'product_id' => $productA->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'quantity' => 1,
        ]));
        $this->cartService->addItem($sessionCart->fresh(), CartItemData::fromArray([
            'product_id' => $productB->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'quantity' => 1,
        ]));

        $clientCart = $this->cartService->getOrCreate($client, null);
        $this->cartService->addItem($clientCart, CartItemData::fromArray([
            'product_id' => $productA->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'quantity' => 2,
        ]));

        $merged = $this->cartService->mergeSessionCartIntoClient('merge-session', $client);

        $this->assertSame($client->id, $merged->client_id);
        $this->assertCount(2, $merged->items);

        $lineA = $merged->items->firstWhere('product_id', $productA->id);
        $lineB = $merged->items->firstWhere('product_id', $productB->id);

        $this->assertNotNull($lineA);
        $this->assertNotNull($lineB);
        $this->assertSame(3, $lineA->quantity);
        $this->assertSame(1, $lineB->quantity);

        $this->assertTrue($sessionCart->fresh()->status === CartStatus::Abandoned);
        $this->assertSame(0, $sessionCart->fresh()->items()->count());
    }
}

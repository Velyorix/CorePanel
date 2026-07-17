<?php

namespace Tests\Feature\Orders;

use Core\Clients\Models\Client;
use Core\Orders\DataTransferObjects\CartItemData;
use Core\Orders\DataTransferObjects\CheckoutDraftData;
use Core\Orders\Enums\CartStatus;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Services\CartService;
use Core\Orders\Services\OrderConversionService;
use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class OrderConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderConversionService $conversionService;

    private CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conversionService = app(OrderConversionService::class);
        $this->cartService = app(CartService::class);

        config([
            'corepanel.billing.tax_preview_rate' => 0.20,
        ]);
    }

    public function test_order_conversion_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(OrderConversionService::class),
            app(OrderConversionService::class),
        );
    }

    public function test_convert_from_checkout_creates_pending_payment_order_and_marks_cart_converted(): void
    {
        $client = Client::factory()->create();
        $product = Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            '19.99',
            '5.00',
        )->create(['slug' => 'convert-vps']);

        $cart = $this->cartService->getOrCreate($client, null);
        $this->cartService->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'quantity' => 2,
            'options' => ['hostname' => 'node-01'],
            'addons' => null,
        ]));

        $draft = CheckoutDraftData::fromArray([
            'contact_name' => 'Alice Client',
            'contact_email' => 'alice@example.test',
            'company_name' => 'Acme',
            'address' => '1 Street',
            'city' => 'Paris',
            'postal_code' => '75001',
            'country' => 'FR',
            'payment_method' => 'manual_transfer',
            'coupon_code' => 'SAVE10',
        ]);

        $order = $this->conversionService->convertFromCheckout($cart->fresh(['items']), $draft);

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(\Core\Orders\Enums\OrderSource::ClientCheckout, $order->source);
        $this->assertNotNull($order->placed_at);
        $this->assertNotNull($order->order_number);
        $this->assertSame(sprintf('ORD-%s-%06d', $order->created_at->format('Ymd'), $order->id), $order->order_number);
        $this->assertSame($client->id, $order->client_id);
        $this->assertSame($cart->id, $order->cart_id);
        $this->assertSame('manual_transfer', $order->payment_method);
        $this->assertSame('SAVE10', $order->coupon_code);
        $this->assertSame('39.98', $order->subtotal_recurring);
        $this->assertSame('5.00', $order->subtotal_setup);
        $this->assertSame('9.00', $order->tax_amount);
        $this->assertSame('53.98', $order->total_amount);
        $this->assertCount(1, $order->items);

        $item = $order->items->first();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame($product->name, $item->product_name);
        $this->assertSame($product->slug, $item->product_slug);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('19.99', $item->unit_price);
        $this->assertSame('5.00', $item->setup_fee);
        $this->assertSame('44.98', $item->line_total);
        $this->assertSame(['hostname' => 'node-01'], $item->options);

        $this->assertTrue($cart->fresh()->status === CartStatus::Converted);
    }

    public function test_convert_rejects_empty_cart(): void
    {
        $client = Client::factory()->create();
        $cart = $this->cartService->getOrCreate($client, null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('empty cart');

        $this->conversionService->convertFromCheckout($cart, $this->validDraft());
    }

    public function test_convert_rejects_session_cart_without_client(): void
    {
        $product = Product::factory()->published()->withPricing()->create(['slug' => 'guest-convert']);
        $cart = $this->cartService->getOrCreate(null, 'guest-session');
        $this->cartService->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('client account is required');

        $this->conversionService->convertFromCheckout($cart->fresh(['items']), $this->validDraft());
    }

    public function test_convert_rejects_already_converted_cart(): void
    {
        $client = Client::factory()->create();
        $product = Product::factory()->published()->withPricing()->create(['slug' => 'twice-convert']);
        $cart = $this->cartService->getOrCreate($client, null);
        $this->cartService->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
        ]));

        $this->conversionService->convertFromCheckout($cart->fresh(['items']), $this->validDraft());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only open carts');

        $this->conversionService->convertFromCheckout($cart->fresh(['items']), $this->validDraft());
    }

    private function validDraft(): CheckoutDraftData
    {
        return CheckoutDraftData::fromArray([
            'contact_name' => 'Alice Client',
            'contact_email' => 'alice@example.test',
            'address' => '1 Street',
            'city' => 'Paris',
            'postal_code' => '75001',
            'country' => 'FR',
            'payment_method' => 'manual_transfer',
        ]);
    }
}

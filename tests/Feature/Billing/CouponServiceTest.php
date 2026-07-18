<?php

namespace Tests\Feature\Billing;

use Core\Billing\Enums\CouponAppliesTo;
use Core\Billing\Enums\CouponType;
use Core\Billing\Exceptions\InvalidCouponException;
use Core\Billing\Models\Coupon;
use Core\Billing\Models\CouponRedemption;
use Core\Billing\Services\CouponService;
use Core\Billing\Services\DiscountCalculator;
use Core\Clients\Models\Client;
use Core\Orders\DataTransferObjects\CartItemData;
use Core\Orders\DataTransferObjects\CheckoutDraftData;
use Core\Orders\Services\CartService;
use Core\Orders\Services\OrderService;
use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    private CouponService $coupons;

    private DiscountCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coupons = app(CouponService::class);
        $this->calculator = app(DiscountCalculator::class);
    }

    public function test_services_are_registered_as_singletons(): void
    {
        $this->assertSame(app(CouponService::class), app(CouponService::class));
        $this->assertSame(app(DiscountCalculator::class), app(DiscountCalculator::class));
    }

    public function test_percent_and_fixed_discount_math(): void
    {
        $percent = Coupon::factory()->percent('PCT10', '10.00')->create();
        $fixed = Coupon::factory()->fixed('FIX5', '5.00')->create();

        $this->assertSame('10.00', $this->calculator->calculate('100.00', $percent)->discountAmount);
        $this->assertSame('90.00', $this->calculator->calculate('100.00', $percent)->discountedSubtotal);
        $this->assertSame('5.00', $this->calculator->calculate('30.00', $fixed)->discountAmount);
        $this->assertSame('5.00', $this->calculator->calculate('20.00', $fixed)->discountAmount);

        $capped = Coupon::factory()->fixed('BIG30', '30.00')->create();
        $this->assertSame('20.00', $this->calculator->calculate('20.00', $capped)->discountAmount);
    }

    public function test_validate_rejects_expired_and_inactive(): void
    {
        $client = Client::factory()->create(['country' => 'FR', 'vat_number' => null]);

        $expired = Coupon::factory()->percent('OLD')->expired()->create();
        $inactive = Coupon::factory()->percent('OFF')->inactive()->create();

        $this->expectException(InvalidCouponException::class);
        $this->coupons->validateForOrder($expired, $client);
    }

    public function test_validate_rejects_inactive_coupon(): void
    {
        $client = Client::factory()->create();
        $inactive = Coupon::factory()->percent('OFF')->inactive()->create();

        $this->expectException(InvalidCouponException::class);
        $this->coupons->validateForOrder($inactive, $client);
    }

    public function test_checkout_applies_coupon_and_redeems(): void
    {
        Coupon::factory()->percent('SAVE10', '10.00')->create();

        $client = Client::factory()->create([
            'country' => 'FR',
            'company_name' => null,
            'vat_number' => null,
        ]);
        $product = Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            '100.00',
            '0.00',
        )->create();

        $cart = app(CartService::class)->getOrCreate($client, null);
        app(CartService::class)->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
        ]));

        $order = app(OrderService::class)->createFromCheckout(
            $cart->fresh(['items.product', 'client']),
            CheckoutDraftData::fromArray([
                'contact_name' => 'Coupon User',
                'contact_email' => 'coupon@example.test',
                'address' => '1 Street',
                'city' => 'Paris',
                'postal_code' => '75001',
                'country' => 'FR',
                'payment_method' => 'manual_transfer',
                'coupon_code' => 'save10',
            ]),
        );

        // 100 - 10% = 90, tax 20% = 18, total 108
        $this->assertSame('SAVE10', $order->coupon_code);
        $this->assertNotNull($order->coupon_id);
        $this->assertSame('10.00', $order->discount_amount);
        $this->assertSame('18.00', $order->tax_amount);
        $this->assertSame('108.00', $order->total_amount);
        $this->assertSame(1, CouponRedemption::query()->count());
        $this->assertSame(1, Coupon::query()->where('code', 'SAVE10')->value('uses_count'));
    }

    public function test_per_client_usage_limit(): void
    {
        $coupon = Coupon::factory()->percent('ONCE', '10.00')->create([
            'max_uses_per_client' => 1,
        ]);
        $client = Client::factory()->create([
            'country' => 'FR',
            'vat_number' => null,
            'company_name' => null,
        ]);
        $product = Product::factory()->published()->withPricing([BillingCycle::Monthly], '50.00')->create();

        $place = function () use ($client, $product) {
            $cart = app(CartService::class)->getOrCreate($client, null);
            app(CartService::class)->addItem($cart, CartItemData::fromArray([
                'product_id' => $product->id,
                'billing_cycle' => BillingCycle::Monthly->value,
            ]));

            return app(OrderService::class)->createFromCheckout(
                $cart->fresh(['items.product', 'client']),
                CheckoutDraftData::fromArray([
                    'contact_name' => 'Once',
                    'contact_email' => 'once@example.test',
                    'address' => '1 Street',
                    'city' => 'Paris',
                    'postal_code' => '75001',
                    'country' => 'FR',
                    'payment_method' => 'manual_transfer',
                    'coupon_code' => 'ONCE',
                ]),
            );
        };

        $place();

        $this->expectException(\InvalidArgumentException::class);
        $place();
    }

    public function test_enum_values(): void
    {
        $this->assertSame(['percent', 'fixed'], CouponType::values());
        $this->assertSame(['order', 'invoice', 'renewal'], CouponAppliesTo::values());
    }

    public function test_invoice_from_order_carries_discount_before_tax(): void
    {
        Coupon::factory()->percent('INV10', '10.00')->create();

        $client = Client::factory()->create([
            'country' => 'FR',
            'company_name' => null,
            'vat_number' => null,
        ]);
        $product = Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            '100.00',
            '0.00',
        )->create();

        $cart = app(CartService::class)->getOrCreate($client, null);
        app(CartService::class)->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
        ]));

        $order = app(OrderService::class)->createFromCheckout(
            $cart->fresh(['items.product', 'client']),
            CheckoutDraftData::fromArray([
                'contact_name' => 'Invoice Coupon',
                'contact_email' => 'inv@example.test',
                'address' => '1 Street',
                'city' => 'Paris',
                'postal_code' => '75001',
                'country' => 'FR',
                'payment_method' => 'manual_transfer',
                'coupon_code' => 'INV10',
            ]),
        );

        $paid = app(OrderService::class)->markPaid($order);
        $invoice = app(\Core\Billing\Services\InvoiceGenerationService::class)->createFromOrder($paid);

        $this->assertSame('100.00', $invoice->subtotal);
        $this->assertSame('10.00', $invoice->discount_amount);
        $this->assertSame('18.00', $invoice->tax_amount);
        $this->assertSame('108.00', $invoice->total_amount);
        $this->assertSame($order->coupon_id, $invoice->coupon_id);
    }
}

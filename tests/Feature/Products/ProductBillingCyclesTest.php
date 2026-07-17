<?php

namespace Tests\Feature\Products;

use Carbon\Carbon;
use Core\Products\DataTransferObjects\ProductData;
use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Core\Products\Models\ProductAddon;
use Core\Products\Models\ProductPricing;
use Core\Products\Services\ProductPricingCalculator;
use Core\Products\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductBillingCyclesTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $productService;

    private ProductPricingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productService = app(ProductService::class);
        $this->calculator = app(ProductPricingCalculator::class);
    }

    public function test_all_tome_cycles_are_supported(): void
    {
        $this->assertSame([
            'hourly',
            'daily',
            'weekly',
            'monthly',
            'quarterly',
            'semi_annual',
            'annual',
            'custom',
        ], BillingCycle::values());

        $this->assertTrue(BillingCycle::Hourly->isOptional());
        $this->assertTrue(BillingCycle::Custom->requiresCustomInterval());
        $this->assertFalse(BillingCycle::Monthly->requiresCustomInterval());
        $this->assertCount(7, BillingCycle::standard());
    }

    public function test_create_product_with_all_standard_cycles_and_setup_fees(): void
    {
        $pricing = [];

        foreach (BillingCycle::standard() as $cycle) {
            $pricing[] = [
                'billing_cycle' => $cycle->value,
                'price' => '10.00',
                'setup_fee' => '5.00',
            ];
        }

        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Full Cycle Plan',
            'slug' => 'full-cycle-plan',
            'pricing' => $pricing,
        ]));

        $this->assertCount(7, $product->pricing);
        $this->assertCount(7, $product->enabledBillingCycles());

        $monthly = $product->pricingFor(BillingCycle::Monthly);
        $this->assertNotNull($monthly);
        $this->assertSame('10.00', $monthly->price);
        $this->assertSame('5.00', $monthly->setup_fee);
        $this->assertSame('15.00', $monthly->firstPaymentTotal());
        $this->assertSame('15.00', $this->calculator->firstPaymentTotal($monthly));
        $this->assertSame('10.00', $this->calculator->recurringAmount($monthly));
    }

    public function test_custom_cycle_requires_interval_days(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom billing cycles require custom_interval_days.');

        ProductData::fromArray([
            'name' => 'Custom Bad',
            'slug' => 'custom-bad',
            'pricing' => [
                [
                    'billing_cycle' => BillingCycle::Custom->value,
                    'price' => '20.00',
                ],
            ],
        ]);
    }

    public function test_custom_cycle_persists_interval_and_advances_period(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Custom Plan',
            'slug' => 'custom-plan',
            'pricing' => [
                [
                    'billing_cycle' => BillingCycle::Custom->value,
                    'custom_interval_days' => 45,
                    'price' => '25.00',
                    'setup_fee' => '10.00',
                ],
            ],
        ]));

        $tier = $product->pricingFor(BillingCycle::Custom);
        $this->assertNotNull($tier);
        $this->assertSame(45, $tier->custom_interval_days);
        $this->assertSame(45, $tier->periodDays());
        $this->assertSame('35.00', $tier->firstPaymentTotal());

        $from = Carbon::parse('2026-01-01 12:00:00');
        $next = BillingCycle::Custom->addPeriod($from, 45);
        $this->assertSame('2026-02-15 12:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_rejects_custom_interval_on_standard_cycle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('custom_interval_days is only allowed for custom billing cycles.');

        ProductData::fromArray([
            'name' => 'Bad Interval',
            'slug' => 'bad-interval',
            'pricing' => [
                [
                    'billing_cycle' => BillingCycle::Monthly->value,
                    'custom_interval_days' => 30,
                    'price' => '10.00',
                ],
            ],
        ]);
    }

    public function test_period_helpers_for_standard_cycles(): void
    {
        $from = Carbon::parse('2026-01-15 10:00:00');

        $this->assertSame('2026-01-15 11:00:00', BillingCycle::Hourly->addPeriod($from)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-16 10:00:00', BillingCycle::Daily->addPeriod($from)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-22 10:00:00', BillingCycle::Weekly->addPeriod($from)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-15 10:00:00', BillingCycle::Monthly->addPeriod($from)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-15 10:00:00', BillingCycle::Quarterly->addPeriod($from)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-15 10:00:00', BillingCycle::SemiAnnual->addPeriod($from)->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-15 10:00:00', BillingCycle::Annual->addPeriod($from)->format('Y-m-d H:i:s'));

        $this->assertSame(1, BillingCycle::Monthly->months());
        $this->assertSame(12, BillingCycle::Annual->months());
        $this->assertNull(BillingCycle::Weekly->months());
    }

    public function test_first_payment_includes_addons_setup_fees(): void
    {
        $product = $this->productService->create(ProductData::fromArray([
            'name' => 'Addon Total',
            'slug' => 'addon-total',
            'pricing' => [
                [
                    'billing_cycle' => BillingCycle::Monthly->value,
                    'price' => '10.00',
                    'setup_fee' => '5.00',
                ],
            ],
            'addons' => [
                [
                    'key' => 'backup',
                    'name' => 'Backup',
                    'price' => '2.00',
                    'setup_fee' => '1.00',
                    'billing_cycle' => BillingCycle::Monthly->value,
                ],
            ],
        ]));

        $addon = $product->addonByKey('backup');
        $this->assertNotNull($addon);

        $this->assertSame(
            '18.00',
            $this->calculator->firstPaymentForProduct($product, BillingCycle::Monthly, [$addon]),
        );
        $this->assertSame(
            '12.00',
            $this->calculator->recurringTotalForProduct($product, BillingCycle::Monthly, [$addon]),
        );
    }

    public function test_configured_breakdown_includes_option_price_deltas(): void
    {
        $product = Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            '10.00',
            '2.00',
        )->create(['slug' => 'option-deltas']);

        \Core\Products\Models\ProductOption::factory()->required()->select([
            ['value' => 'small', 'label' => 'Small', 'price_delta' => 0],
            ['value' => 'large', 'label' => 'Large', 'price_delta' => 5.5],
        ])->create([
            'product_id' => $product->id,
            'key' => 'size',
            'name' => 'Size',
        ]);

        $product = $product->fresh(['options', 'pricing', 'addons']);

        $breakdown = $this->calculator->configuredBreakdown(
            $product,
            BillingCycle::Monthly,
            ['size' => 'large'],
            [],
            1,
        );

        $this->assertSame('10.00', $breakdown['base_price']);
        $this->assertSame('5.50', $breakdown['option_deltas']);
        $this->assertSame('15.50', $breakdown['unit_price']);
        $this->assertSame('2.00', $breakdown['setup_fee']);
        $this->assertSame('17.50', $breakdown['first_payment_subtotal']);
    }

    public function test_calculator_rejects_disabled_pricing(): void
    {
        $product = Product::factory()->create(['slug' => 'disabled-price']);
        ProductPricing::factory()->disabled()->create([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly,
            'price' => '10.00',
            'setup_fee' => '0.00',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No enabled pricing found for billing cycle [monthly].');

        $this->calculator->firstPaymentForProduct($product->fresh('pricing'), BillingCycle::Monthly);
    }

    public function test_pricing_calculator_is_singleton(): void
    {
        $this->assertSame(
            app(ProductPricingCalculator::class),
            app(ProductPricingCalculator::class),
        );
    }
}

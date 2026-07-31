<?php

namespace Tests\Feature\Billing;

use Carbon\Carbon;
use Core\Billing\DataTransferObjects\ProrataCalculationInput;
use Core\Billing\Enums\ProrataDirection;
use Core\Billing\Exceptions\InvalidProrataException;
use Core\Billing\Services\ProrataCalculationService;
use Core\Products\Enums\BillingCycle;
use Tests\TestCase;

class ProrataCalculationServiceTest extends TestCase
{
    private ProrataCalculationService $prorata;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prorata = app(ProrataCalculationService::class);
    }

    public function test_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ProrataCalculationService::class),
            app(ProrataCalculationService::class),
        );
    }

    public function test_upgrade_charges_net_difference_for_remaining_days(): void
    {
        // Period 30 days; change with 15 days left → half period.
        $result = $this->prorata->calculate(new ProrataCalculationInput(
            oldPeriodAmount: '100.00',
            newPeriodAmount: '200.00',
            periodStart: Carbon::parse('2026-01-01'),
            periodEnd: Carbon::parse('2026-01-31'),
            changeAt: Carbon::parse('2026-01-16'),
            billingCycle: BillingCycle::Monthly,
        ));

        $this->assertSame(30, $result->periodDays);
        $this->assertSame(15, $result->daysRemaining);
        $this->assertSame('50.00', $result->unusedCredit);
        $this->assertSame('100.00', $result->newCharge);
        $this->assertSame('50.00', $result->netAmount);
        $this->assertSame(ProrataDirection::Charge, $result->direction);
        $this->assertTrue($result->isCharge());
    }

    public function test_downgrade_credits_net_difference_for_remaining_days(): void
    {
        $result = $this->prorata->calculate(new ProrataCalculationInput(
            oldPeriodAmount: '200.00',
            newPeriodAmount: '100.00',
            periodStart: Carbon::parse('2026-01-01'),
            periodEnd: Carbon::parse('2026-01-31'),
            changeAt: Carbon::parse('2026-01-16'),
            billingCycle: BillingCycle::Monthly,
        ));

        $this->assertSame('100.00', $result->unusedCredit);
        $this->assertSame('50.00', $result->newCharge);
        $this->assertSame('50.00', $result->netAmount);
        $this->assertSame(ProrataDirection::Credit, $result->direction);
        $this->assertTrue($result->isCredit());
    }

    public function test_same_price_yields_none_direction(): void
    {
        $result = $this->prorata->calculate(new ProrataCalculationInput(
            oldPeriodAmount: '80.00',
            newPeriodAmount: '80.00',
            periodStart: Carbon::parse('2026-03-01'),
            periodEnd: Carbon::parse('2026-03-31'),
            changeAt: Carbon::parse('2026-03-11'),
            billingCycle: BillingCycle::Monthly,
        ));

        $this->assertSame(ProrataDirection::None, $result->direction);
        $this->assertSame('0.00', $result->netAmount);
        $this->assertSame($result->unusedCredit, $result->newCharge);
    }

    public function test_change_on_period_end_yields_zero_remaining(): void
    {
        $result = $this->prorata->calculate(new ProrataCalculationInput(
            oldPeriodAmount: '100.00',
            newPeriodAmount: '200.00',
            periodStart: Carbon::parse('2026-01-01'),
            periodEnd: Carbon::parse('2026-01-31'),
            changeAt: Carbon::parse('2026-01-31'),
            billingCycle: BillingCycle::Monthly,
        ));

        $this->assertSame(0, $result->daysRemaining);
        $this->assertSame('0.00', $result->unusedCredit);
        $this->assertSame('0.00', $result->newCharge);
        $this->assertSame('0.00', $result->netAmount);
        $this->assertSame(ProrataDirection::None, $result->direction);
    }

    public function test_custom_cycle_requires_interval(): void
    {
        $this->expectException(InvalidProrataException::class);

        $this->prorata->calculate(new ProrataCalculationInput(
            oldPeriodAmount: '10.00',
            newPeriodAmount: '20.00',
            periodStart: Carbon::parse('2026-01-01'),
            periodEnd: Carbon::parse('2026-01-15'),
            changeAt: Carbon::parse('2026-01-05'),
            billingCycle: BillingCycle::Custom,
            customIntervalDays: null,
        ));
    }

    public function test_custom_cycle_with_interval_succeeds(): void
    {
        $result = $this->prorata->calculate(new ProrataCalculationInput(
            oldPeriodAmount: '14.00',
            newPeriodAmount: '28.00',
            periodStart: Carbon::parse('2026-01-01'),
            periodEnd: Carbon::parse('2026-01-15'),
            changeAt: Carbon::parse('2026-01-08'),
            billingCycle: BillingCycle::Custom,
            customIntervalDays: 14,
        ));

        $this->assertSame(14, $result->periodDays);
        $this->assertSame(7, $result->daysRemaining);
        $this->assertSame('7.00', $result->unusedCredit);
        $this->assertSame('14.00', $result->newCharge);
        $this->assertSame('7.00', $result->netAmount);
        $this->assertSame(ProrataDirection::Charge, $result->direction);
    }

    public function test_rejects_negative_amounts(): void
    {
        $this->expectException(InvalidProrataException::class);

        $this->prorata->calculate(new ProrataCalculationInput(
            oldPeriodAmount: '-1.00',
            newPeriodAmount: '10.00',
            periodStart: Carbon::parse('2026-01-01'),
            periodEnd: Carbon::parse('2026-01-31'),
            changeAt: Carbon::parse('2026-01-10'),
            billingCycle: BillingCycle::Monthly,
        ));
    }

    public function test_rejects_inverted_period(): void
    {
        $this->expectException(InvalidProrataException::class);

        $this->prorata->calculate(new ProrataCalculationInput(
            oldPeriodAmount: '10.00',
            newPeriodAmount: '20.00',
            periodStart: Carbon::parse('2026-02-01'),
            periodEnd: Carbon::parse('2026-01-01'),
            changeAt: Carbon::parse('2026-01-15'),
            billingCycle: BillingCycle::Monthly,
        ));
    }

    public function test_rejects_change_before_period_start(): void
    {
        $this->expectException(InvalidProrataException::class);

        $this->prorata->calculate(new ProrataCalculationInput(
            oldPeriodAmount: '10.00',
            newPeriodAmount: '20.00',
            periodStart: Carbon::parse('2026-01-10'),
            periodEnd: Carbon::parse('2026-02-10'),
            changeAt: Carbon::parse('2026-01-01'),
            billingCycle: BillingCycle::Monthly,
        ));
    }

    public function test_direction_enum_values(): void
    {
        $this->assertSame(['charge', 'credit', 'none'], ProrataDirection::values());
    }
}

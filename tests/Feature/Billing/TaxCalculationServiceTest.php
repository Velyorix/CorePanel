<?php

namespace Tests\Feature\Billing;

use Core\Billing\DataTransferObjects\TaxAddress;
use Core\Billing\DataTransferObjects\TaxLineInput;
use Core\Billing\Enums\TaxRuleType;
use Core\Billing\Models\TaxRule;
use Core\Billing\Services\TaxCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private TaxCalculationService $taxCalculation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taxCalculation = app(TaxCalculationService::class);

        TaxRule::query()->where('country', 'DE')->delete();
        TaxRule::factory()->deStandard()->create();
    }

    public function test_tax_calculation_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(TaxCalculationService::class),
            app(TaxCalculationService::class),
        );
    }

    public function test_fr_b2c_applies_standard_vat(): void
    {
        $result = $this->taxCalculation->calculateLine(
            '100.00',
            new TaxAddress('FR'),
        );

        $this->assertSame('20.00', $result->taxAmount);
        $this->assertSame('0.2000', $result->taxRate);
        $this->assertSame('standard', $result->application);
        $this->assertFalse($result->isReverseCharge);
    }

    public function test_same_country_b2b_still_applies_vat(): void
    {
        $result = $this->taxCalculation->calculateLine(
            '100.00',
            new TaxAddress('FR', 'FR12345678901', 'Acme SAS'),
        );

        $this->assertSame('20.00', $result->taxAmount);
        $this->assertSame('standard', $result->application);
    }

    public function test_eu_cross_border_b2b_uses_reverse_charge(): void
    {
        $result = $this->taxCalculation->calculateLine(
            '100.00',
            new TaxAddress('DE', 'DE123456789', 'German GmbH'),
        );

        $this->assertSame('0.00', $result->taxAmount);
        $this->assertSame('0.0000', $result->taxRate);
        $this->assertSame('reverse_charge', $result->application);
        $this->assertTrue($result->isReverseCharge);
    }

    public function test_non_eu_export_is_zero(): void
    {
        $result = $this->taxCalculation->calculateLine(
            '100.00',
            new TaxAddress('US'),
        );

        $this->assertSame('0.00', $result->taxAmount);
        $this->assertSame('export', $result->application);
    }

    public function test_missing_country_uses_preview_fallback(): void
    {
        config([
            'corepanel.billing.tax_preview_rate' => 0.10,
            'corepanel.billing.tax_preview_label' => 'Preview tax',
        ]);

        $result = $this->taxCalculation->calculateLine(
            '100.00',
            new TaxAddress(null),
        );

        $this->assertSame('10.00', $result->taxAmount);
        $this->assertSame('fallback', $result->application);
        $this->assertSame('Preview tax', $result->taxLabel);
    }

    public function test_inactive_rule_is_skipped(): void
    {
        TaxRule::query()->where('country', 'BE')->delete();
        TaxRule::factory()->create([
            'country' => 'BE',
            'rate' => '0.2100',
            'type' => TaxRuleType::Standard,
            'active' => false,
        ]);

        $result = $this->taxCalculation->calculateLine(
            '100.00',
            new TaxAddress('BE'),
        );

        // Falls back to null-country default (0.20 from migration).
        $this->assertSame('20.00', $result->taxAmount);
        $this->assertSame('standard', $result->application);
    }

    public function test_calculate_document_sums_lines(): void
    {
        $document = $this->taxCalculation->calculateDocument([
            new TaxLineInput('10.00'),
            new TaxLineInput('15.00'),
        ], new TaxAddress('FR'));

        $this->assertSame('25.00', $document->subtotal);
        $this->assertSame('5.00', $document->taxAmount);
        $this->assertSame('30.00', $document->total);
        $this->assertCount(2, $document->lines);
        $this->assertSame('2.00', $document->lines[0]->taxAmount);
        $this->assertSame('3.00', $document->lines[1]->taxAmount);
    }

    public function test_de_b2c_uses_country_rate(): void
    {
        $result = $this->taxCalculation->calculateLine(
            '100.00',
            new TaxAddress('DE'),
        );

        $this->assertSame('19.00', $result->taxAmount);
        $this->assertSame('0.1900', $result->taxRate);
    }
}

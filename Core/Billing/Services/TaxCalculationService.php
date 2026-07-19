<?php

namespace Core\Billing\Services;

use Core\Billing\DataTransferObjects\TaxAddress;
use Core\Billing\DataTransferObjects\TaxCalculationResult;
use Core\Billing\DataTransferObjects\TaxDocumentResult;
use Core\Billing\DataTransferObjects\TaxLineInput;
use Core\Billing\Enums\TaxRuleType;
use Core\Billing\Models\TaxRule;

/**
 * Resolves VAT rates and amounts from tax_rules and billing address context.
 */
class TaxCalculationService
{
    /**
     * Resolve the effective rate for an address without applying it to an amount.
     */
    public function resolveRate(TaxAddress $address): TaxCalculationResult
    {
        return $this->calculateLine('0.00', $address);
    }

    public function calculateLine(string $taxableAmount, TaxAddress $address): TaxCalculationResult
    {
        $resolved = $this->resolveApplication($address);
        $taxable = $this->money((float) $taxableAmount);
        $taxAmount = $this->money((float) $taxable * $resolved['rate']);

        return new TaxCalculationResult(
            taxableAmount: $taxable,
            taxAmount: $taxAmount,
            taxRate: $this->rateString($resolved['rate']),
            taxLabel: $resolved['label'],
            application: $resolved['application'],
            isReverseCharge: $resolved['application'] === 'reverse_charge',
        );
    }

    /**
     * @param  list<TaxLineInput>  $lines
     */
    public function calculateDocument(array $lines, TaxAddress $address): TaxDocumentResult
    {
        $resolved = $this->resolveApplication($address);
        $lineResults = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lines as $line) {
            if (! $line instanceof TaxLineInput) {
                continue;
            }

            $taxable = $this->money((float) $line->taxableAmount);
            $taxAmount = $this->money((float) $taxable * $resolved['rate']);

            $lineResults[] = new TaxCalculationResult(
                taxableAmount: $taxable,
                taxAmount: $taxAmount,
                taxRate: $this->rateString($resolved['rate']),
                taxLabel: $resolved['label'],
                application: $resolved['application'],
                isReverseCharge: $resolved['application'] === 'reverse_charge',
            );

            $subtotal += (float) $taxable;
            $taxTotal += (float) $taxAmount;
        }

        $subtotalMoney = $this->money($subtotal);
        $taxMoney = $this->money($taxTotal);

        return new TaxDocumentResult(
            subtotal: $subtotalMoney,
            taxAmount: $taxMoney,
            total: $this->money((float) $subtotalMoney + (float) $taxMoney),
            effectiveRate: $this->rateString($resolved['rate']),
            taxLabel: $resolved['label'],
            application: $resolved['application'],
            isReverseCharge: $resolved['application'] === 'reverse_charge',
            lines: $lineResults,
        );
    }

    public function findActiveRule(?string $country, TaxRuleType $type = TaxRuleType::Standard): ?TaxRule
    {
        $normalized = $country !== null ? strtoupper(trim($country)) : null;

        if ($normalized !== null && strlen($normalized) === 2) {
            $rule = TaxRule::query()
                ->active()
                ->where('country', $normalized)
                ->where('type', $type)
                ->first();

            if ($rule !== null) {
                return $rule;
            }
        }

        return TaxRule::query()
            ->active()
            ->whereNull('country')
            ->where('type', $type)
            ->first();
    }

    /**
     * @return array{rate: float, label: string, application: string}
     */
    private function resolveApplication(TaxAddress $address): array
    {
        $country = $address->normalizedCountry();

        if ($country === null) {
            $fallbackRate = max(0, (float) config('corepanel.billing.tax_preview_rate', 0));

            return [
                'rate' => $fallbackRate,
                'label' => $this->fallbackLabel($fallbackRate),
                'application' => 'fallback',
            ];
        }

        if (! $this->isEuCountry($country)) {
            return [
                'rate' => 0.0,
                'label' => (string) __('Export (0%)'),
                'application' => 'export',
            ];
        }

        $sellerCountry = $this->sellerCountry();

        if (
            $this->isBusinessWithVat($address)
            && $country !== $sellerCountry
        ) {
            return [
                'rate' => 0.0,
                'label' => (string) __('Reverse charge'),
                'application' => 'reverse_charge',
            ];
        }

        $rule = $this->findActiveRule($country, TaxRuleType::Standard);

        if ($rule === null) {
            return [
                'rate' => 0.0,
                'label' => (string) __('No tax'),
                'application' => 'none',
            ];
        }

        if ($rule->type === TaxRuleType::Exempt || (float) $rule->rate <= 0) {
            return [
                'rate' => 0.0,
                'label' => (string) __('Tax exempt'),
                'application' => 'exempt',
            ];
        }

        $rate = (float) $rule->rate;
        $percent = rtrim(rtrim(number_format($rate * 100, 2, '.', ''), '0'), '.');

        return [
            'rate' => $rate,
            'label' => (string) __('VAT :rate%', ['rate' => $percent]),
            'application' => 'standard',
        ];
    }

    private function isBusinessWithVat(TaxAddress $address): bool
    {
        $company = trim((string) ($address->companyName ?? ''));
        $vat = $address->normalizedVatNumber();

        if ($company === '' || $vat === null) {
            return false;
        }

        return (bool) preg_match('/^[A-Z]{2}[A-Z0-9]{2,12}$/', $vat);
    }

    private function isEuCountry(string $country): bool
    {
        /** @var list<string>|mixed $countries */
        $countries = config('corepanel.billing.eu_countries', []);

        if (! is_array($countries)) {
            return false;
        }

        $normalized = array_map(
            static fn (mixed $code): string => strtoupper((string) $code),
            $countries,
        );

        return in_array(strtoupper($country), $normalized, true);
    }

    private function sellerCountry(): string
    {
        $country = strtoupper(trim((string) config('corepanel.billing.seller_country', 'FR')));

        return strlen($country) === 2 ? $country : 'FR';
    }

    private function fallbackLabel(float $rate): string
    {
        $label = config('corepanel.billing.tax_preview_label');

        if (is_string($label) && $label !== '') {
            return $label;
        }

        if ($rate <= 0) {
            return (string) __('Tax (estimate)');
        }

        $percent = rtrim(rtrim(number_format($rate * 100, 2, '.', ''), '0'), '.');

        return (string) __('VAT :rate% (estimate)', ['rate' => $percent]);
    }

    private function rateString(float $rate): string
    {
        return number_format(max(0, $rate), 4, '.', '');
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}

<?php

namespace Database\Seeders;

use Core\Billing\Enums\TaxRuleType;
use Core\Billing\Models\TaxRule;
use Illuminate\Database\Seeder;

class TaxRuleSeeder extends Seeder
{
    /**
     * Seed common EU standard VAT rates.
     */
    public function run(): void
    {
        $rates = [
            'FR' => '0.2000',
            'DE' => '0.1900',
            'BE' => '0.2100',
            'ES' => '0.2100',
            'IT' => '0.2200',
            'NL' => '0.2100',
            'LU' => '0.1700',
            'PT' => '0.2300',
        ];

        foreach ($rates as $country => $rate) {
            TaxRule::query()->updateOrCreate(
                [
                    'country' => $country,
                    'type' => TaxRuleType::Standard,
                ],
                [
                    'rate' => $rate,
                    'active' => true,
                ],
            );
        }

        TaxRule::query()->updateOrCreate(
            [
                'country' => null,
                'type' => TaxRuleType::Standard,
            ],
            [
                'rate' => '0.2000',
                'active' => true,
            ],
        );
    }
}

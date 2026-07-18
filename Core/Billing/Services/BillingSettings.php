<?php

namespace Core\Billing\Services;

use Core\Settings\Models\Setting;

/**
 * Mutable billing preferences with config fallbacks (roadmap 12.3).
 * Admin UI for these settings arrives in 12.16.
 */
class BillingSettings
{
    public const INVOICE_PREFIX = 'billing.invoice.prefix';

    public function invoicePrefix(): string
    {
        $setting = Setting::query()->where('key', self::INVOICE_PREFIX)->first();

        if ($setting !== null && filled($setting->value)) {
            return trim((string) $setting->value);
        }

        return (string) config('corepanel.billing.invoice_numbering.prefix', 'INV');
    }

    public function setInvoicePrefix(string $prefix): void
    {
        $prefix = trim($prefix);

        Setting::query()->updateOrCreate(
            ['key' => self::INVOICE_PREFIX],
            [
                'value' => $prefix,
                'type' => 'string',
                'autoload' => true,
                'updated_at' => now(),
            ],
        );
    }
}

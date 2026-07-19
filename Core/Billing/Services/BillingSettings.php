<?php

namespace Core\Billing\Services;

use Core\Settings\Models\Setting;

/**
 * Mutable billing preferences with config fallbacks.
 */
class BillingSettings
{
    public const INVOICE_PREFIX = 'billing.invoice.prefix';

    public const QUOTE_PREFIX = 'billing.quote.prefix';

    public const CREDIT_NOTE_PREFIX = 'billing.credit_note.prefix';

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

    public function quotePrefix(): string
    {
        $setting = Setting::query()->where('key', self::QUOTE_PREFIX)->first();

        if ($setting !== null && filled($setting->value)) {
            return trim((string) $setting->value);
        }

        return (string) config('corepanel.billing.quote_numbering.prefix', 'QUO');
    }

    public function setQuotePrefix(string $prefix): void
    {
        $prefix = trim($prefix);

        Setting::query()->updateOrCreate(
            ['key' => self::QUOTE_PREFIX],
            [
                'value' => $prefix,
                'type' => 'string',
                'autoload' => true,
                'updated_at' => now(),
            ],
        );
    }

    public function creditNotePrefix(): string
    {
        $setting = Setting::query()->where('key', self::CREDIT_NOTE_PREFIX)->first();

        if ($setting !== null && filled($setting->value)) {
            return trim((string) $setting->value);
        }

        return (string) config('corepanel.billing.credit_note_numbering.prefix', 'CN');
    }

    public function setCreditNotePrefix(string $prefix): void
    {
        $prefix = trim($prefix);

        Setting::query()->updateOrCreate(
            ['key' => self::CREDIT_NOTE_PREFIX],
            [
                'value' => $prefix,
                'type' => 'string',
                'autoload' => true,
                'updated_at' => now(),
            ],
        );
    }

    /**
     * Seller letterhead data for PDF documents.
     *
     * @return array{
     *     name: string,
     *     address: string|null,
     *     city: string|null,
     *     postal_code: string|null,
     *     country: string|null,
     *     vat_number: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     logo_path: string|null,
     *     footer: string|null
     * }
     */
    public function seller(): array
    {
        $config = config('corepanel.billing.seller', []);

        return [
            'name' => filled($config['name'] ?? null)
                ? (string) $config['name']
                : 'CorePanel',
            'address' => $this->nullableString($config['address'] ?? null),
            'city' => $this->nullableString($config['city'] ?? null),
            'postal_code' => $this->nullableString($config['postal_code'] ?? null),
            'country' => $this->nullableString(
                $config['country'] ?? config('corepanel.billing.seller_country'),
            ),
            'vat_number' => $this->nullableString($config['vat_number'] ?? null),
            'email' => $this->nullableString($config['email'] ?? null),
            'phone' => $this->nullableString($config['phone'] ?? null),
            'logo_path' => $this->nullableString($config['logo_path'] ?? null),
            'footer' => $this->nullableString($config['footer'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}

<?php

namespace Core\Billing\DataTransferObjects;

use Core\Clients\Models\Client;
use Core\Orders\DataTransferObjects\CheckoutDraftData;
use Core\Orders\Models\Order;

final readonly class TaxAddress
{
    public function __construct(
        public ?string $country,
        public ?string $vatNumber = null,
        public ?string $companyName = null,
    ) {
    }

    public static function fromClient(Client $client): self
    {
        return new self(
            country: self::normalizeCountry($client->country),
            vatNumber: self::nullableString($client->vat_number),
            companyName: self::nullableString($client->company_name),
        );
    }

    public static function fromDraft(CheckoutDraftData $draft): self
    {
        return new self(
            country: self::normalizeCountry($draft->country),
            vatNumber: self::nullableString($draft->vatNumber),
            companyName: self::nullableString($draft->companyName),
        );
    }

    public static function fromOrder(Order $order): self
    {
        return new self(
            country: self::normalizeCountry($order->country),
            vatNumber: self::nullableString($order->vat_number),
            companyName: self::nullableString($order->company_name),
        );
    }

    public function normalizedCountry(): ?string
    {
        return self::normalizeCountry($this->country);
    }

    public function normalizedVatNumber(): ?string
    {
        $vat = self::nullableString($this->vatNumber);

        if ($vat === null) {
            return null;
        }

        return strtoupper(preg_replace('/[\s.\-]/', '', $vat) ?? $vat);
    }

    private static function normalizeCountry(?string $country): ?string
    {
        $value = self::nullableString($country);

        if ($value === null) {
            return null;
        }

        $value = strtoupper($value);

        return strlen($value) === 2 ? $value : null;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}

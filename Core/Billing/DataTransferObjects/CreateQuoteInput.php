<?php

namespace Core\Billing\DataTransferObjects;

use Carbon\CarbonInterface;
use Core\Clients\Models\Client;

final readonly class CreateQuoteInput
{
    /**
     * @param  list<QuoteLineInput>  $lines
     */
    public function __construct(
        public int $clientId,
        public array $lines,
        public string $currency = 'EUR',
        public ?string $contactName = null,
        public ?string $contactEmail = null,
        public ?string $companyName = null,
        public ?string $vatNumber = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $country = null,
        public ?string $postalCode = null,
        public ?string $phone = null,
        public ?string $notes = null,
        public ?CarbonInterface $validUntil = null,
    ) {
    }

    /**
     * @param  list<QuoteLineInput>  $lines
     */
    public static function fromClient(
        Client $client,
        array $lines,
        string $currency = 'EUR',
        ?string $notes = null,
        ?CarbonInterface $validUntil = null,
    ): self {
        $client->loadMissing('owner');

        return new self(
            clientId: $client->id,
            lines: $lines,
            currency: $currency,
            contactName: $client->owner?->name,
            contactEmail: $client->owner?->email,
            companyName: $client->company_name,
            vatNumber: $client->vat_number,
            address: $client->address,
            city: $client->city,
            country: $client->country !== null ? strtoupper((string) $client->country) : null,
            postalCode: $client->postal_code,
            phone: $client->phone,
            notes: $notes,
            validUntil: $validUntil,
        );
    }

    public function taxAddress(): TaxAddress
    {
        return new TaxAddress(
            country: $this->country,
            vatNumber: $this->vatNumber,
            companyName: $this->companyName,
        );
    }
}

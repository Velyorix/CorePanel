<?php

namespace Core\Billing\DataTransferObjects;

use Carbon\CarbonInterface;
use Core\Clients\Models\Client;

final readonly class RenewalInvoiceInput
{
    public function __construct(
        public int $serviceId,
        public int $clientId,
        public CarbonInterface $billingPeriodEnd,
        public RenewalLineInput $line,
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
        public ?int $createdBy = null,
    ) {
    }

    public static function fromClient(
        Client $client,
        int $serviceId,
        CarbonInterface $billingPeriodEnd,
        RenewalLineInput $line,
        string $currency = 'EUR',
        ?string $notes = null,
        ?int $createdBy = null,
    ): self {
        $client->loadMissing('owner');

        return new self(
            serviceId: $serviceId,
            clientId: $client->id,
            billingPeriodEnd: $billingPeriodEnd,
            line: $line,
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
            createdBy: $createdBy,
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

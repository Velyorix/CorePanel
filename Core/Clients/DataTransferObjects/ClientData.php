<?php

namespace Core\Clients\DataTransferObjects;

use Core\Clients\Enums\ClientStatus;

readonly class ClientData
{
    public function __construct(
        public ?int $userId,
        public ?string $companyName,
        public ?string $vatNumber,
        public ?string $address,
        public ?string $city,
        public ?string $country,
        public ?string $postalCode,
        public ?string $phone,
        public ClientStatus $status = ClientStatus::Active,
    ) {
    }

    /**
     * @param  array{
     *     user_id?: int|null,
     *     company_name?: string|null,
     *     vat_number?: string|null,
     *     address?: string|null,
     *     city?: string|null,
     *     country?: string|null,
     *     postal_code?: string|null,
     *     phone?: string|null,
     *     status?: string|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $status = ClientStatus::tryFrom((string) ($data['status'] ?? ClientStatus::Active->value))
            ?? ClientStatus::Active;

        return new self(
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            companyName: self::nullableString($data['company_name'] ?? null),
            vatNumber: self::nullableString($data['vat_number'] ?? null),
            address: self::nullableString($data['address'] ?? null),
            city: self::nullableString($data['city'] ?? null),
            country: self::nullableCountry($data['country'] ?? null),
            postalCode: self::nullableString($data['postal_code'] ?? null),
            phone: self::nullableString($data['phone'] ?? null),
            status: $status,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'user_id' => $this->userId,
            'company_name' => $this->companyName,
            'vat_number' => $this->vatNumber,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'postal_code' => $this->postalCode,
            'phone' => $this->phone,
            'status' => $this->status->value,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private static function nullableCountry(mixed $value): ?string
    {
        $country = self::nullableString($value);

        return $country === null ? null : strtoupper($country);
    }
}

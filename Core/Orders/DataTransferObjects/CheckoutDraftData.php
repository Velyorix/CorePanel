<?php

namespace Core\Orders\DataTransferObjects;

use InvalidArgumentException;

readonly class CheckoutDraftData
{
    /**
     * @param  array{
     *     company_name?: string|null,
     *     vat_number?: string|null,
     *     address?: string|null,
     *     city?: string|null,
     *     country?: string|null,
     *     postal_code?: string|null,
     *     phone?: string|null,
     *     contact_name?: string|null,
     *     contact_email?: string|null,
     *     coupon_code?: string|null,
     *     payment_method?: string|null
     * }  $data
     */
    public function __construct(
        public ?string $companyName = null,
        public ?string $vatNumber = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $country = null,
        public ?string $postalCode = null,
        public ?string $phone = null,
        public ?string $contactName = null,
        public ?string $contactEmail = null,
        public ?string $couponCode = null,
        public ?string $paymentMethod = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $email = self::nullableString($data['contact_email'] ?? null);

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('The contact email must be a valid email address.');
        }

        $country = self::nullableString($data['country'] ?? null);
        if ($country !== null) {
            $country = strtoupper($country);
            if (strlen($country) !== 2) {
                throw new InvalidArgumentException('The country must be a 2-letter ISO code.');
            }
        }

        return new self(
            companyName: self::nullableString($data['company_name'] ?? null),
            vatNumber: self::nullableString($data['vat_number'] ?? null),
            address: self::nullableString($data['address'] ?? null),
            city: self::nullableString($data['city'] ?? null),
            country: $country,
            postalCode: self::nullableString($data['postal_code'] ?? null),
            phone: self::nullableString($data['phone'] ?? null),
            contactName: self::nullableString($data['contact_name'] ?? null),
            contactEmail: $email,
            couponCode: self::nullableString($data['coupon_code'] ?? null),
            paymentMethod: self::nullableString($data['payment_method'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'company_name' => $this->companyName,
            'vat_number' => $this->vatNumber,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'postal_code' => $this->postalCode,
            'phone' => $this->phone,
            'contact_name' => $this->contactName,
            'contact_email' => $this->contactEmail,
            'coupon_code' => $this->couponCode,
            'payment_method' => $this->paymentMethod,
        ];
    }

    public function withCouponCode(?string $couponCode): self
    {
        return self::fromArray([
            ...$this->toArray(),
            'coupon_code' => $couponCode,
        ]);
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

<?php

namespace Core\Orders\Services;

use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Core\Orders\DataTransferObjects\CheckoutDraftData;
use Illuminate\Contracts\Session\Session;

/**
 * Session-backed checkout draft (roadmap 10.7).
 * Order conversion arrives in étape 10.8; real coupons/gateways later.
 */
class CheckoutDraftService
{
    public const SESSION_KEY = 'corepanel.checkout.draft';

    public function load(Session $session): ?CheckoutDraftData
    {
        $payload = $session->get(self::SESSION_KEY);

        if (! is_array($payload) || $payload === []) {
            return null;
        }

        return CheckoutDraftData::fromArray($payload);
    }

    public function store(Session $session, CheckoutDraftData $draft): void
    {
        $session->put(self::SESSION_KEY, $draft->toArray());
    }

    public function clear(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }

    public function prefill(?Client $client, ?User $user, ?CheckoutDraftData $existing = null): CheckoutDraftData
    {
        $base = $existing?->toArray() ?? [];

        return CheckoutDraftData::fromArray([
            'company_name' => $base['company_name'] ?? $client?->company_name,
            'vat_number' => $base['vat_number'] ?? $client?->vat_number,
            'address' => $base['address'] ?? $client?->address,
            'city' => $base['city'] ?? $client?->city,
            'country' => $base['country'] ?? $client?->country,
            'postal_code' => $base['postal_code'] ?? $client?->postal_code,
            'phone' => $base['phone'] ?? $client?->phone,
            'contact_name' => $base['contact_name'] ?? $user?->name,
            'contact_email' => $base['contact_email'] ?? $user?->email,
            'coupon_code' => $base['coupon_code'] ?? null,
            'payment_method' => $base['payment_method']
                ?? $this->defaultPaymentMethodKey(),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, enabled: bool, hint: string|null}>
     */
    public function paymentMethods(): array
    {
        $configured = config('corepanel.checkout.payment_methods', []);

        if (! is_array($configured)) {
            return [];
        }

        $methods = [];

        foreach ($configured as $method) {
            if (! is_array($method) || blank($method['key'] ?? null)) {
                continue;
            }

            $methods[] = [
                'key' => (string) $method['key'],
                'label' => (string) ($method['label'] ?? $method['key']),
                'enabled' => (bool) ($method['enabled'] ?? false),
                'hint' => isset($method['hint']) && filled($method['hint'])
                    ? (string) $method['hint']
                    : null,
            ];
        }

        return $methods;
    }

    /**
     * @return list<string>
     */
    public function enabledPaymentMethodKeys(): array
    {
        return array_values(array_map(
            static fn (array $method): string => $method['key'],
            array_filter(
                $this->paymentMethods(),
                static fn (array $method): bool => $method['enabled'],
            ),
        ));
    }

    public function defaultPaymentMethodKey(): ?string
    {
        $keys = $this->enabledPaymentMethodKeys();

        return $keys[0] ?? null;
    }

    public function isCouponUiEnabled(): bool
    {
        return (bool) config('corepanel.checkout.coupon_enabled', true);
    }
}

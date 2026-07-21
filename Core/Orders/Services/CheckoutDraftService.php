<?php

namespace Core\Orders\Services;

use Core\Auth\Models\User;
use Core\Billing\Services\GatewayManager;
use Core\Clients\Models\Client;
use Core\Orders\DataTransferObjects\CheckoutDraftData;
use Illuminate\Contracts\Session\Session;

/**
 * Session-backed checkout draft for client billing details and payment method.
 */
class CheckoutDraftService
{
    public const SESSION_KEY = 'corepanel.checkout.draft';

    public function __construct(
        private readonly GatewayManager $gateways,
    ) {
    }

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
        $enabledKeys = $this->enabledPaymentMethodKeys();
        $paymentMethod = $base['payment_method'] ?? null;

        if (! is_string($paymentMethod) || ! in_array($paymentMethod, $enabledKeys, true)) {
            $paymentMethod = $this->defaultPaymentMethodKey();
        }

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
            'payment_method' => $paymentMethod,
        ]);
    }

    /**
     * Active gateways registered in the runtime registry and enabled in DB.
     *
     * @return list<array{key: string, label: string, enabled: bool, hint: string|null}>
     */
    public function paymentMethods(): array
    {
        $methods = [];

        foreach ($this->gateways->enabled() as $gateway) {
            $methods[] = [
                'key' => $gateway->key(),
                'label' => $gateway->label(),
                'enabled' => true,
                'hint' => null,
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
            $this->paymentMethods(),
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

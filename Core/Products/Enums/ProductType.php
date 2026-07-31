<?php

namespace Core\Products\Enums;

enum ProductType: string
{
    case Hosting = 'hosting';
    case Vps = 'vps';
    case Game = 'game';
    case Domain = 'domain';
    case Addon = 'addon';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Hosting => __('Hosting'),
            self::Vps => __('VPS'),
            self::Game => __('Game server'),
            self::Domain => __('Domain'),
            self::Addon => __('Addon'),
            self::Other => __('Other'),
        };
    }

    /**
     * Whether ordering this product typically requires a hostname / FQDN.
     */
    public function requiresHostname(): bool
    {
        return match ($this) {
            self::Hosting, self::Vps, self::Game, self::Domain => true,
            self::Addon, self::Other => false,
        };
    }

    /**
     * Canonical option key used for hostname / domain capture on configure.
     */
    public function hostnameOptionKey(): ?string
    {
        if (! $this->requiresHostname()) {
            return null;
        }

        return $this === self::Domain ? 'domain' : 'hostname';
    }

    public function hostnameOptionLabel(): ?string
    {
        return match ($this->hostnameOptionKey()) {
            'domain' => __('Domain name'),
            'hostname' => __('Hostname'),
            default => null,
        };
    }

    public function isAddon(): bool
    {
        return $this === self::Addon;
    }

    /**
     * Types that are billable standalone catalog products (not add-ons).
     *
     * @return list<self>
     */
    public static function catalogTypes(): array
    {
        return [
            self::Hosting,
            self::Vps,
            self::Game,
            self::Domain,
            self::Other,
        ];
    }
}

<?php

namespace Core\Orders\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    /** Awaiting payment after checkout. */
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::PendingPayment => __('Pending payment'),
            self::Paid => __('Paid'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function isPlaced(): bool
    {
        return $this !== self::Draft;
    }

    public function isOpen(): bool
    {
        return match ($this) {
            self::Draft, self::PendingPayment => true,
            self::Paid, self::Cancelled => false,
        };
    }

    /**
     * Lifecycle graph for OrderService.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingPayment, self::Cancelled],
            self::PendingPayment => [self::Paid, self::Cancelled],
            self::Paid, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

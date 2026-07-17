<?php

namespace Core\Products\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

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
            self::Draft => __('Draft'),
            self::Published => __('Published'),
            self::Archived => __('Archived'),
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Published, self::Archived],
            self::Published => [self::Draft, self::Archived],
            self::Archived => [self::Draft],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return in_array($target, $this->allowedTransitions(), true);
    }
}

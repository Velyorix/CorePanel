<?php

namespace Core\Providers\Enums;

enum ProviderOperationStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function isSuccessful(): bool
    {
        return $this === self::Success;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

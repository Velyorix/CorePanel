<?php

namespace Core\Modules\Enums;

/**
 * High-level role of a module package.
 */
enum ModuleProfile: string
{
    case Extension = 'extension';
    case Integration = 'integration';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Extension => __('Extension module'),
            self::Integration => __('Integration module'),
            self::Hybrid => __('Hybrid module'),
        };
    }
}

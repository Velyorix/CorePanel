<?php

namespace Core\Nodes\Enums;

enum NodeType: string
{
    case General = 'general';
    case Game = 'game';
    case Vps = 'vps';
    case Web = 'web';
    case Dedicated = 'dedicated';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::General => __('General'),
            self::Game => __('Game'),
            self::Vps => __('VPS'),
            self::Web => __('Web'),
            self::Dedicated => __('Dedicated'),
            self::Custom => __('Custom'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

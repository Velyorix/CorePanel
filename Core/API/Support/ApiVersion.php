<?php

namespace Core\API\Support;

final class ApiVersion
{
    public const CURRENT = 'v1';

    public static function current(): string
    {
        return (string) config('corepanel.api.version', self::CURRENT);
    }
}

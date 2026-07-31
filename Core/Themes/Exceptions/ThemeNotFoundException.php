<?php

namespace Core\Themes\Exceptions;

use RuntimeException;

class ThemeNotFoundException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Theme [{$key}] was not found.");
    }
}

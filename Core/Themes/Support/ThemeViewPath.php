<?php

namespace Core\Themes\Support;

/**
 * Converts dotted view names to theme view file paths.
 */
final class ThemeViewPath
{
    public static function normalize(string $name): string
    {
        $name = trim(str_replace('\\', '/', $name), '/');
        $name = preg_replace('/\.blade\.(php)?$/', '', $name) ?? $name;
        $name = str_replace('.', '/', $name);

        if ($name === '') {
            throw new \InvalidArgumentException('View name cannot be empty.');
        }

        return $name.'.blade.php';
    }

    public static function forLayout(string $name): string
    {
        $name = trim(str_replace('\\', '/', $name), '/');
        $name = preg_replace('/\.blade\.(php)?$/', '', $name) ?? $name;
        $name = preg_replace('#^components/layout/#', '', $name) ?? $name;
        $name = str_replace('.', '/', $name);

        if ($name === '') {
            throw new \InvalidArgumentException('Layout name cannot be empty.');
        }

        return 'components/layout/'.$name.'.blade.php';
    }

    public static function forComponent(string $name): string
    {
        $relative = self::normalize($name);

        if (str_starts_with($relative, 'components/')) {
            return $relative;
        }

        return 'components/'.$relative;
    }

    public static function forPartial(string $name): string
    {
        $relative = self::normalize($name);

        if (str_starts_with($relative, 'components/')) {
            return $relative;
        }

        return 'components/'.$relative;
    }
}

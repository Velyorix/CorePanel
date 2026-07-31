<?php

namespace Core\Themes\Support;

use Core\Themes\Exceptions\InvalidThemeManifestException;
use Core\Themes\Exceptions\ThemeScaffoldException;
use JsonException;

/**
 * Reads and updates theme.json manifest files.
 */
final class ThemeManifestWriter
{
    /**
     * @return array<string, mixed>
     */
    public static function read(string $themeDirectory): array
    {
        $manifestPath = $themeDirectory.DIRECTORY_SEPARATOR.'theme.json';

        if (! is_file($manifestPath)) {
            throw InvalidThemeManifestException::missingManifest(basename($themeDirectory));
        }

        $contents = file_get_contents($manifestPath);

        if ($contents === false) {
            throw InvalidThemeManifestException::unreadableManifest(basename($themeDirectory));
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidThemeManifestException::invalidJson(basename($themeDirectory), $exception);
        }

        if (! is_array($data)) {
            throw InvalidThemeManifestException::invalidStructure(basename($themeDirectory));
        }

        return $data;
    }

    public static function write(string $themeDirectory, array $data): void
    {
        $manifestPath = $themeDirectory.DIRECTORY_SEPARATOR.'theme.json';
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false || file_put_contents($manifestPath, $encoded.PHP_EOL) === false) {
            throw ThemeScaffoldException::writeFailed($manifestPath);
        }
    }

    public static function addAssetEntry(string $themeDirectory, string $relativeEntry): bool
    {
        $relativeEntry = trim(str_replace('\\', '/', $relativeEntry), '/');

        if ($relativeEntry === '' || str_contains($relativeEntry, '..')) {
            throw new \InvalidArgumentException('Asset entry path is invalid.');
        }

        $data = self::read($themeDirectory);
        $assets = is_array($data['assets'] ?? null) ? $data['assets'] : [];
        $entries = is_array($assets['entries'] ?? null) ? $assets['entries'] : [];

        if (in_array($relativeEntry, $entries, true)) {
            return false;
        }

        $entries[] = $relativeEntry;
        sort($entries);
        $assets['entries'] = array_values($entries);
        $data['assets'] = $assets;

        self::write($themeDirectory, $data);

        return true;
    }
}

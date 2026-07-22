<?php

namespace Core\Themes\Support;

/**
 * Resolves theme asset entry paths declared in theme.json.
 */
final class ThemeAssetManifest
{
    /** @var list<string> */
    private const CONVENTION_CSS = [
        'resources/css/theme.css',
        'resources/css/app.css',
    ];

    /** @var list<string> */
    private const CONVENTION_JS = [
        'resources/js/theme.js',
        'resources/js/app.js',
    ];

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<string> Paths relative to the theme package root.
     */
    public static function entriesFromManifest(array $manifest, string $themeDirectory): array
    {
        $assets = $manifest['assets'] ?? null;

        if (is_array($assets) && array_key_exists('entries', $assets)) {
            if (! is_array($assets['entries'])) {
                return [];
            }

            return self::filterExistingEntries(
                $themeDirectory,
                self::normalizeEntryList($assets['entries']),
            );
        }

        return self::conventionEntries($themeDirectory);
    }

    /**
     * @return list<string>
     */
    public static function conventionEntries(string $themeDirectory): array
    {
        $entries = [];

        foreach (self::CONVENTION_CSS as $css) {
            if (is_file($themeDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $css))) {
                $entries[] = $css;
                break;
            }
        }

        foreach (self::CONVENTION_JS as $js) {
            if (is_file($themeDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $js))) {
                $entries[] = $js;
                break;
            }
        }

        return $entries;
    }

    /**
     * @param  list<string>  $entries
     * @return list<string>
     */
    public static function filterExistingEntries(string $themeDirectory, array $entries): array
    {
        $existing = [];

        foreach ($entries as $entry) {
            $absolute = $themeDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entry);

            if (is_file($absolute)) {
                $existing[] = $entry;
            }
        }

        return $existing;
    }

    /**
     * @param  list<string>  $entries Relative to the theme package root.
     * @return list<string> Relative to the application root (Vite input paths).
     */
    public static function toProjectRelativeEntries(string $themeDirectory, array $entries): array
    {
        $projectRelative = [];

        foreach (self::filterExistingEntries($themeDirectory, $entries) as $entry) {
            $absolute = $themeDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entry);
            $relative = ltrim(str_replace('\\', '/', substr($absolute, strlen(base_path()))), '/');

            if ($relative !== '') {
                $projectRelative[] = $relative;
            }
        }

        return $projectRelative;
    }

    /**
     * @param  mixed  $entries
     * @return list<string>
     */
    private static function normalizeEntryList(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $entry = trim(str_replace('\\', '/', $entry), '/');

            if ($entry === '' || str_contains($entry, '..')) {
                continue;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }
}

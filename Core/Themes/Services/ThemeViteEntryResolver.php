<?php

namespace Core\Themes\Services;

use Core\Themes\DataTransferObjects\ThemeDescriptor;
use Core\Themes\Support\ThemeAssetManifest;
use Illuminate\Contracts\Session\Session;

/**
 * Resolves Vite entry paths for the core bundle and active theme assets.
 */
class ThemeViteEntryResolver
{
    public function __construct(
        private readonly ThemeManager $themes,
    ) {
    }

    /**
     * Runtime entries for @vite (core bundle + effective theme chain).
     *
     * @return list<string>
     */
    public function resolve(?Session $session = null): array
    {
        $entries = $this->coreEntries();
        $themeKey = $this->themes->effectiveKey($session);

        if ($themeKey === null) {
            return $this->uniqueEntries($entries);
        }

        $theme = $this->themes->findOrFail($themeKey);

        return $this->uniqueEntries([
            ...$entries,
            ...$this->entriesForChain($this->themes->resolveInheritanceChain($theme)),
        ]);
    }

    /**
     * All entries that must exist in the Vite build manifest.
     *
     * @return list<string>
     */
    public function allBuildEntries(): array
    {
        $entries = $this->coreEntries();

        foreach ($this->themes->discover() as $theme) {
            $entries = [...$entries, ...$theme->projectRelativeAssetEntries()];
        }

        return $this->uniqueEntries($entries);
    }

    /**
     * @param  list<ThemeDescriptor>  $chain
     * @return list<string>
     */
    public function entriesForChain(array $chain): array
    {
        $entries = [];

        foreach ($chain as $descriptor) {
            $entries = [...$entries, ...$descriptor->projectRelativeAssetEntries()];
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    public function coreEntries(): array
    {
        $configured = config('corepanel.themes.vite.core_entries', [
            'resources/css/app.css',
            'resources/js/app.js',
        ]);

        if (! is_array($configured)) {
            return ['resources/css/app.css', 'resources/js/app.js'];
        }

        $entries = [];

        foreach ($configured as $entry) {
            if (! is_string($entry) || trim($entry) === '') {
                continue;
            }

            $entries[] = str_replace('\\', '/', trim($entry));
        }

        return $entries !== [] ? $entries : ['resources/css/app.css', 'resources/js/app.js'];
    }

    /**
     * @param  list<string>  $entries
     * @return list<string>
     */
    private function uniqueEntries(array $entries): array
    {
        $unique = [];

        foreach ($entries as $entry) {
            $entry = str_replace('\\', '/', $entry);

            if (! in_array($entry, $unique, true)) {
                $unique[] = $entry;
            }
        }

        return $unique;
    }
}

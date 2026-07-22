<?php

namespace Core\Themes\Services;

use Core\Themes\Exceptions\ThemeNotFoundException;
use Core\Themes\Exceptions\ThemeViteBuildException;
use Illuminate\Support\Facades\Process;

/**
 * Runs Vite build/watch commands with theme-scoped entry filters.
 */
class ThemeViteBuilder
{
    public function __construct(
        private readonly ThemeViteEntryResolver $entries,
        private readonly ThemeManager $themes,
    ) {
    }

    public function build(?string $themeKey = null): int
    {
        return $this->run([], $themeKey);
    }

    public function watch(?string $themeKey = null): int
    {
        return $this->run([], $themeKey, devServer: true);
    }

    /**
     * @return list<string>
     */
    public function previewEntries(?string $themeKey = null): array
    {
        return $this->entries->buildEntries($this->normalizeThemeKey($themeKey));
    }

    /**
     * @param  list<string>  $arguments
     */
    private function run(array $arguments, ?string $themeKey, bool $devServer = false): int
    {
        $themeKey = $this->normalizeThemeKey($themeKey);
        $viteEntries = $this->entries->buildEntries($themeKey);
        $command = $this->command($arguments, $devServer);

        $process = Process::path(base_path())
            ->forever()
            ->env($this->environment($themeKey, $viteEntries))
            ->run($command, function (string $type, string $output): void {
                echo $output;
            });

        if (! $process->successful()) {
            throw ThemeViteBuildException::processFailed(
                implode(' ', $command),
                $process->exitCode() ?? 1,
            );
        }

        return $process->exitCode() ?? 0;
    }

    private function normalizeThemeKey(?string $themeKey): ?string
    {
        if (! is_string($themeKey) || trim($themeKey) === '') {
            return null;
        }

        $themeKey = trim($themeKey);

        if ($this->themes->find($themeKey) === null) {
            throw ThemeNotFoundException::forKey($themeKey);
        }

        return $themeKey;
    }

    /**
     * @param  list<string>  $arguments
     * @return list<string>
     */
    private function command(array $arguments, bool $devServer): array
    {
        $binary = (string) config('corepanel.themes.vite.binary', 'npx');
        $vitePackage = (string) config('corepanel.themes.vite.package', 'vite');

        if ($devServer) {
            return array_values(array_filter([$binary, $vitePackage, ...$arguments]));
        }

        return array_values(array_filter([$binary, $vitePackage, 'build', ...$arguments]));
    }

    /**
     * @param  list<string>  $viteEntries
     * @return array<string, string>
     */
    private function environment(?string $themeKey, array $viteEntries): array
    {
        $environment = [
            'COREPANEL_THEMES_PATH' => $this->themesPath(),
        ];

        if ($themeKey !== null) {
            $environment['COREPANEL_VITE_INPUTS'] = implode(',', $viteEntries);
        }

        return $environment;
    }

    private function themesPath(): string
    {
        $configured = config('corepanel.themes.path');

        if (! is_string($configured) || $configured === '') {
            return base_path('Themes');
        }

        return $configured;
    }
}

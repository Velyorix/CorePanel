<?php

namespace Core\Themes\Console;

use Core\Themes\Services\ThemeGenerator;

class ThemeMakeAssetCommand extends ThemeGeneratorCommand
{
    protected $signature = 'theme:make:asset
                            {name : Asset basename without extension}
                            {--theme= : Theme key (defaults to the active theme)}
                            {--type=css : Asset type (css or js)}';

    protected $description = 'Create a CSS/JS asset and register it in theme.json';

    public function handle(): int
    {
        return $this->handleGeneration(function (ThemeGenerator $generator): array {
            $theme = $generator->resolveTheme($this->themeKeyOption());

            return $generator->makeAsset(
                $theme,
                (string) $this->argument('name'),
                (string) $this->option('type'),
            );
        });
    }
}

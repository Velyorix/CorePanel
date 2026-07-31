<?php

namespace Core\Themes\Console;

use Core\Themes\Services\ThemeGenerator;

class ThemeMakeLayoutCommand extends ThemeGeneratorCommand
{
    protected $signature = 'theme:make:layout
                            {name : Layout name (e.g. admin, client, guest)}
                            {--theme= : Theme key (defaults to the active theme)}';

    protected $description = 'Create a layout override under components/layout';

    public function handle(): int
    {
        return $this->handleGeneration(function (ThemeGenerator $generator): string {
            $theme = $generator->resolveTheme($this->themeKeyOption());

            return $generator->makeLayout($theme, (string) $this->argument('name'));
        });
    }
}

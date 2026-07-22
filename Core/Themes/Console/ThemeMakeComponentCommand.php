<?php

namespace Core\Themes\Console;

use Core\Themes\Services\ThemeGenerator;

class ThemeMakeComponentCommand extends ThemeGeneratorCommand
{
    protected $signature = 'theme:make:component
                            {name : Dotted component path (e.g. ui.button)}
                            {--theme= : Theme key (defaults to the active theme)}';

    protected $description = 'Create a Blade component override inside a theme package';

    public function handle(): int
    {
        return $this->handleGeneration(function (ThemeGenerator $generator): string {
            $theme = $generator->resolveTheme($this->themeKeyOption());

            return $generator->makeComponent($theme, (string) $this->argument('name'));
        });
    }
}

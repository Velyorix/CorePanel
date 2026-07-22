<?php

namespace Core\Themes\Console;

use Core\Themes\Services\ThemeGenerator;

class ThemeMakeViewCommand extends ThemeGeneratorCommand
{
    protected $signature = 'theme:make:view
                            {name : Dotted view path (e.g. admin.clients.index)}
                            {--theme= : Theme key (defaults to the active theme)}';

    protected $description = 'Create a Blade view override inside a theme package';

    public function handle(): int
    {
        return $this->handleGeneration(function (ThemeGenerator $generator): string {
            $theme = $generator->resolveTheme($this->themeKeyOption());

            return $generator->makeView($theme, (string) $this->argument('name'));
        });
    }
}

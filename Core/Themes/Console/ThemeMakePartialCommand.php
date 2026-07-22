<?php

namespace Core\Themes\Console;

use Core\Themes\Services\ThemeGenerator;

class ThemeMakePartialCommand extends ThemeGeneratorCommand
{
    protected $signature = 'theme:make:partial
                            {name : Partial name or dotted path}
                            {--theme= : Theme key (defaults to the active theme)}';

    protected $description = 'Create a reusable Blade partial inside a theme package';

    public function handle(): int
    {
        return $this->handleGeneration(function (ThemeGenerator $generator): string {
            $theme = $generator->resolveTheme($this->themeKeyOption());

            return $generator->makePartial($theme, (string) $this->argument('name'));
        });
    }
}

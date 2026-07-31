<?php

namespace Core\Themes\Console;

use Core\Themes\Exceptions\ThemeNotFoundException;
use Core\Themes\Exceptions\ThemeViteBuildException;
use Core\Themes\Services\ThemeViteBuilder;
use Illuminate\Console\Command;
use Throwable;

class ThemeBuildCommand extends Command
{
    protected $signature = 'theme:build
                            {theme? : Theme key to compile (defaults to all discovered themes)}';

    protected $description = 'Compile theme assets via Vite';

    public function handle(ThemeViteBuilder $builder): int
    {
        $theme = $this->argument('theme');
        $themeKey = is_string($theme) && trim($theme) !== '' ? trim($theme) : null;

        try {
            $entries = $builder->previewEntries($themeKey);

            if ($themeKey !== null) {
                $this->components->info("Building assets for theme [{$themeKey}]...");
            } else {
                $this->components->info('Building core and all discovered theme assets...');
            }

            $this->line('  <fg=gray>Entries:</> '.implode(', ', $entries));

            $builder->build($themeKey);

            $this->components->info('Theme assets compiled successfully.');

            return self::SUCCESS;
        } catch (ThemeNotFoundException|ThemeViteBuildException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error(__('Unable to compile theme assets.'));
            report($exception);

            return self::FAILURE;
        }
    }
}

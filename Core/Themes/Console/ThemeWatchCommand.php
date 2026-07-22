<?php

namespace Core\Themes\Console;

use Core\Themes\Exceptions\ThemeNotFoundException;
use Core\Themes\Exceptions\ThemeViteBuildException;
use Core\Themes\Services\ThemeViteBuilder;
use Illuminate\Console\Command;
use Throwable;

class ThemeWatchCommand extends Command
{
    protected $signature = 'theme:watch
                            {theme? : Theme key to watch (defaults to all discovered themes)}';

    protected $description = 'Start the Vite dev server for theme assets';

    public function handle(ThemeViteBuilder $builder): int
    {
        $theme = $this->argument('theme');
        $themeKey = is_string($theme) && trim($theme) !== '' ? trim($theme) : null;

        try {
            $entries = $builder->previewEntries($themeKey);

            if ($themeKey !== null) {
                $this->components->info("Starting Vite dev server for theme [{$themeKey}]...");
            } else {
                $this->components->info('Starting Vite dev server for all theme assets...');
            }

            $this->line('  <fg=gray>Entries:</> '.implode(', ', $entries));
            $this->newLine();

            return $builder->watch($themeKey) === 0
                ? self::SUCCESS
                : self::FAILURE;
        } catch (ThemeNotFoundException|ThemeViteBuildException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error(__('Unable to start theme asset watcher.'));
            report($exception);

            return self::FAILURE;
        }
    }
}

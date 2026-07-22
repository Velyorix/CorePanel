<?php

namespace Core\Themes\Console;

use Core\Themes\Exceptions\ThemeScaffoldException;
use Core\Themes\Services\ThemeGenerator;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

abstract class ThemeGeneratorCommand extends Command
{
    protected function handleGeneration(callable $callback): int
    {
        try {
            $path = $callback(app(ThemeGenerator::class));
        } catch (InvalidArgumentException|ThemeScaffoldException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error(__('Unable to generate theme file.'));
            report($exception);

            return self::FAILURE;
        }

        if (is_array($path)) {
            $this->components->info('Theme asset created successfully.');
            $this->line("  <fg=gray>File:</> {$path['path']}");

            if ($path['manifest_updated']) {
                $this->line("  <fg=gray>Manifest:</> added {$path['relative_entry']}");
            } else {
                $this->line("  <fg=gray>Manifest:</> {$path['relative_entry']} already registered");
            }

            $this->newLine();
            $this->components->warn('Run `npm run build` or `npm run dev` to compile theme assets.');

            return self::SUCCESS;
        }

        $this->components->info('Theme file created successfully.');
        $this->line("  <fg=gray>Path:</> {$path}");

        return self::SUCCESS;
    }

    protected function themeKeyOption(): ?string
    {
        $theme = $this->option('theme');

        return is_string($theme) && trim($theme) !== '' ? trim($theme) : null;
    }
}

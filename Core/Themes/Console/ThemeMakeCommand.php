<?php

namespace Core\Themes\Console;

use Core\Themes\Exceptions\ThemeScaffoldException;
use Core\Themes\Services\ThemeScaffolder;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class ThemeMakeCommand extends Command
{
    protected $signature = 'theme:make
                            {name : The display name for the theme package}
                            {--key= : Manifest theme key (defaults to a slug derived from the name)}
                            {--label= : Manifest display label}
                            {--author= : Author shown in theme.json}
                            {--description= : Short description for theme.json}
                            {--theme-version=1.0.0 : Initial semver version}';

    protected $description = 'Scaffold a new theme package';

    public function handle(ThemeScaffolder $scaffolder): int
    {
        try {
            $result = $scaffolder->make((string) $this->argument('name'), [
                'key' => $this->option('key'),
                'label' => $this->option('label'),
                'author' => $this->option('author'),
                'description' => $this->option('description'),
                'version' => $this->option('theme-version'),
            ]);
        } catch (InvalidArgumentException|ThemeScaffoldException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error(__('Unable to scaffold theme package.'));
            report($exception);

            return self::FAILURE;
        }

        $descriptor = $result['descriptor'];

        $this->components->info("Theme [{$descriptor->key}] created successfully.");
        $this->newLine();
        $this->line("  <fg=gray>Path:</> {$result['path']}");
        $this->line("  <fg=gray>Label:</> {$descriptor->label}");
        $this->newLine();
        $this->line('  <fg=gray>Files:</>');

        foreach ($result['files'] as $file) {
            $this->line('    '.$file);
        }

        $this->newLine();
        $this->components->warn('Run `npm run build` or `npm run dev` to compile theme assets.');

        return self::SUCCESS;
    }
}

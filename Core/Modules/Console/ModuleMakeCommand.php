<?php

namespace Core\Modules\Console;

use Core\Modules\Exceptions\ModuleScaffoldException;
use Core\Modules\Services\ModuleScaffolder;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class ModuleMakeCommand extends Command
{
    protected $signature = 'module:make
                            {name : The display name for the module package}
                            {--key= : Manifest module key (defaults to a slug derived from the name)}
                            {--label= : Manifest display label}
                            {--profile=integration : Scaffold profile: integration, extension, or payment_gateway}
                            {--capability=* : Additional capabilities to merge into module.json}
                            {--author= : Author name shown in module.json}
                            {--email= : Author email shown in module.json}
                            {--description= : Short description for module.json}
                            {--module-version=1.0.0 : Initial semver version}';

    protected $description = 'Scaffold a new module package';

    public function handle(ModuleScaffolder $scaffolder): int
    {
        try {
            $result = $scaffolder->make((string) $this->argument('name'), [
                'key' => $this->option('key'),
                'label' => $this->option('label'),
                'profile' => $this->option('profile'),
                'capabilities' => $this->option('capability'),
                'author' => $this->option('author'),
                'email' => $this->option('email'),
                'description' => $this->option('description'),
                'version' => $this->option('module-version'),
            ]);
        } catch (InvalidArgumentException|ModuleScaffoldException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error(__('Unable to scaffold module package.'));
            report($exception);

            return self::FAILURE;
        }

        $manifest = $result['manifest'];

        $this->components->info("Module [{$manifest->key}] created successfully.");
        $this->newLine();
        $this->line("  <fg=gray>Path:</> {$result['path']}");
        $this->line("  <fg=gray>Profile:</> {$result['profile']->label()}");
        $this->line("  <fg=gray>Label:</> {$manifest->name}");
        $this->newLine();
        $this->line('  <fg=gray>Files:</>');

        foreach ($result['files'] as $file) {
            $this->line('    '.$file);
        }

        $this->newLine();
        $this->components->warn('Enable the module from Admin → Modules or set COREPANEL_MODULES_ENABLED='.$manifest->key);

        return self::SUCCESS;
    }
}

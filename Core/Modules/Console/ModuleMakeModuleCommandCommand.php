<?php

namespace Core\Modules\Console;

use Core\Modules\Services\ModuleGenerator;

class ModuleMakeModuleCommandCommand extends ModuleGeneratorCommand
{
    protected $signature = 'module:make:command
                            {name : Artisan command class basename}
                            {--module= : Target module key}';

    protected $description = 'Create an Artisan command inside a module package';

    public function handle(): int
    {
        return $this->handleGeneration(function (ModuleGenerator $generator): array {
            $module = $generator->resolveModule($this->moduleKeyOption());

            return $generator->makeCommand($module, (string) $this->argument('name'));
        });
    }
}

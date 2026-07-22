<?php

namespace Core\Modules\Console;

use Core\Modules\Services\ModuleGenerator;

class ModuleMakeFactoryCommand extends ModuleGeneratorCommand
{
    protected $signature = 'module:make:factory
                            {name : Model class basename for the factory}
                            {--module= : Target module key}';

    protected $description = 'Create a model factory inside a module package';

    public function handle(): int
    {
        return $this->handleGeneration(function (ModuleGenerator $generator): array {
            $module = $generator->resolveModule($this->moduleKeyOption());

            return $generator->makeFactory($module, (string) $this->argument('name'));
        });
    }
}

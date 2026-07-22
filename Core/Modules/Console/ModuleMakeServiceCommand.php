<?php

namespace Core\Modules\Console;

use Core\Modules\Services\ModuleGenerator;

class ModuleMakeServiceCommand extends ModuleGeneratorCommand
{
    protected $signature = 'module:make:service
                            {name : Service class basename}
                            {--module= : Target module key}';

    protected $description = 'Create a service class inside a module package';

    public function handle(): int
    {
        return $this->handleGeneration(function (ModuleGenerator $generator): string {
            $module = $generator->resolveModule($this->moduleKeyOption());

            return $generator->makeService($module, (string) $this->argument('name'));
        });
    }
}

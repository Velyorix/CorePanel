<?php

namespace Core\Modules\Console;

use Core\Modules\Services\ModuleGenerator;

class ModuleMakeProviderCommand extends ModuleGeneratorCommand
{
    protected $signature = 'module:make:provider
                            {name : Service provider class basename}
                            {--module= : Target module key}';

    protected $description = 'Create a service provider and register it in module.json';

    public function handle(): int
    {
        return $this->handleGeneration(function (ModuleGenerator $generator): array {
            $module = $generator->resolveModule($this->moduleKeyOption());

            return $generator->makeProvider($module, (string) $this->argument('name'));
        });
    }
}

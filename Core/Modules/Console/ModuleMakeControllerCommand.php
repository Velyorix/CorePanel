<?php

namespace Core\Modules\Console;

use Core\Modules\Services\ModuleGenerator;

class ModuleMakeControllerCommand extends ModuleGeneratorCommand
{
    protected $signature = 'module:make:controller
                            {name : Controller class basename}
                            {--module= : Target module key}';

    protected $description = 'Create an HTTP controller inside a module package';

    public function handle(): int
    {
        return $this->handleGeneration(function (ModuleGenerator $generator): string {
            $module = $generator->resolveModule($this->moduleKeyOption());

            return $generator->makeController($module, (string) $this->argument('name'));
        });
    }
}

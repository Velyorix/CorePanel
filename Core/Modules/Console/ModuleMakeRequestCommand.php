<?php

namespace Core\Modules\Console;

use Core\Modules\Services\ModuleGenerator;

class ModuleMakeRequestCommand extends ModuleGeneratorCommand
{
    protected $signature = 'module:make:request
                            {name : Form request class basename}
                            {--module= : Target module key}';

    protected $description = 'Create a form request inside a module package';

    public function handle(): int
    {
        return $this->handleGeneration(function (ModuleGenerator $generator): array {
            $module = $generator->resolveModule($this->moduleKeyOption());

            return $generator->makeRequest($module, (string) $this->argument('name'));
        });
    }
}

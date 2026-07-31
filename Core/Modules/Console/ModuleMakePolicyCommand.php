<?php

namespace Core\Modules\Console;

use Core\Modules\Services\ModuleGenerator;

class ModuleMakePolicyCommand extends ModuleGeneratorCommand
{
    protected $signature = 'module:make:policy
                            {name : Policy class basename (e.g. Item or ItemPolicy)}
                            {--module= : Target module key}';

    protected $description = 'Create a policy and register a module permission in module.json';

    public function handle(): int
    {
        return $this->handleGeneration(function (ModuleGenerator $generator): array {
            $module = $generator->resolveModule($this->moduleKeyOption());

            return $generator->makePolicy($module, (string) $this->argument('name'));
        });
    }
}

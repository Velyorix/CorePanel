<?php

namespace Core\Modules\Console;

use Core\Modules\Services\ModuleGenerator;

class ModuleMakeTraitCommand extends ModuleGeneratorCommand
{
    protected $signature = 'module:make:trait
                            {name : Trait class basename}
                            {--module= : Target module key}';

    protected $description = 'Create a PHP trait inside a module package';

    public function handle(): int
    {
        return $this->handleGeneration(function (ModuleGenerator $generator): array {
            $module = $generator->resolveModule($this->moduleKeyOption());

            return $generator->makeTrait($module, (string) $this->argument('name'));
        });
    }
}

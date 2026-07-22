<?php

namespace Core\Modules\Console;

use Core\Modules\Services\ModuleGenerator;

class ModuleMakeModelCommand extends ModuleGeneratorCommand
{
    protected $signature = 'module:make:model
                            {name : Model class basename}
                            {--module= : Target module key}
                            {--migration : Also create a migration for the model table}';

    protected $description = 'Create an Eloquent model inside a module package';

    public function handle(): int
    {
        return $this->handleGeneration(function (ModuleGenerator $generator): array {
            $module = $generator->resolveModule($this->moduleKeyOption());

            return $generator->makeModel(
                $module,
                (string) $this->argument('name'),
                (bool) $this->option('migration'),
            );
        });
    }
}

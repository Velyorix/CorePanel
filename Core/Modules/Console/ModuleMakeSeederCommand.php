<?php

namespace Core\Modules\Console;

use Core\Modules\Services\ModuleGenerator;

class ModuleMakeSeederCommand extends ModuleGeneratorCommand
{
    protected $signature = 'module:make:seeder
                            {name : Seeder class basename}
                            {--module= : Target module key}';

    protected $description = 'Create a database seeder inside a module package';

    public function handle(): int
    {
        return $this->handleGeneration(function (ModuleGenerator $generator): array {
            $module = $generator->resolveModule($this->moduleKeyOption());

            return $generator->makeSeeder($module, (string) $this->argument('name'));
        });
    }
}

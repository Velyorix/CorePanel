<?php

namespace Core\Modules\Console;

use Core\Modules\Services\ModuleGenerator;

class ModuleMakeMigrationCommand extends ModuleGeneratorCommand
{
    protected $signature = 'module:make:migration
                            {name : Migration table suffix (e.g. create_items_table or items)}
                            {--module= : Target module key}';

    protected $description = 'Create a migration inside a module package';

    public function handle(): int
    {
        return $this->handleGeneration(function (ModuleGenerator $generator): string {
            $module = $generator->resolveModule($this->moduleKeyOption());

            return $generator->makeMigration($module, (string) $this->argument('name'));
        });
    }
}

<?php

namespace Core\Modules\Console;

use Core\Modules\Services\ModuleGenerator;

class ModuleMakeGatewayCommand extends ModuleGeneratorCommand
{
    protected $signature = 'module:make:gateway
                            {name : Payment gateway class basename}
                            {--module= : Target module key}';

    protected $description = 'Create a payment gateway stub and register it in module.json';

    public function handle(): int
    {
        return $this->handleGeneration(function (ModuleGenerator $generator): array {
            $module = $generator->resolveModule($this->moduleKeyOption());

            return $generator->makeGateway($module, (string) $this->argument('name'));
        });
    }
}

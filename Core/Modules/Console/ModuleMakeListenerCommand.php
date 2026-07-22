<?php

namespace Core\Modules\Console;

use Core\Modules\Services\ModuleGenerator;

class ModuleMakeListenerCommand extends ModuleGeneratorCommand
{
    protected $signature = 'module:make:listener
                            {name : Listener class basename}
                            {--module= : Target module key}
                            {--event= : Event class (short name or FQCN)}';

    protected $description = 'Create an event listener inside a module package';

    public function handle(): int
    {
        return $this->handleGeneration(function (ModuleGenerator $generator): array {
            $module = $generator->resolveModule($this->moduleKeyOption());
            $event = $this->option('event');

            return $generator->makeListener(
                $module,
                (string) $this->argument('name'),
                is_string($event) && trim($event) !== '' ? trim($event) : null,
            );
        });
    }
}

<?php

namespace Core\Modules\Console;

use Core\Modules\Services\ModuleGenerator;

class ModuleMakeControllerCommand extends ModuleGeneratorCommand
{
    protected $signature = 'module:make:controller
                            {name : Controller class basename}
                            {--module= : Target module key}
                            {--admin : Register a route in routes/admin.php}
                            {--client : Register a route in routes/client.php}
                            {--api : Register a route in routes/api.php}';

    protected $description = 'Create an HTTP controller inside a module package';

    public function handle(): int
    {
        return $this->handleGeneration(function (ModuleGenerator $generator): array {
            $module = $generator->resolveModule($this->moduleKeyOption());

            return $generator->makeController(
                $module,
                (string) $this->argument('name'),
                $this->routeScope(),
            );
        });
    }

    private function routeScope(): ?string
    {
        foreach (['admin', 'client', 'api'] as $scope) {
            if ((bool) $this->option($scope)) {
                return $scope;
            }
        }

        return null;
    }
}

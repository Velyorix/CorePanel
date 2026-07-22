<?php

namespace Core\Modules\Console;

use Core\Modules\Exceptions\ModuleScaffoldException;
use Core\Modules\Services\ModuleGenerator;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

abstract class ModuleGeneratorCommand extends Command
{
    protected function handleGeneration(callable $callback): int
    {
        try {
            $path = $callback(app(ModuleGenerator::class));
        } catch (InvalidArgumentException|ModuleScaffoldException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error(__('Unable to generate module file.'));
            report($exception);

            return self::FAILURE;
        }

        $this->components->info('Module file created successfully.');
        $this->line("  <fg=gray>Path:</> {$path}");

        return self::SUCCESS;
    }

    protected function moduleKeyOption(): ?string
    {
        $module = $this->option('module');

        return is_string($module) && trim($module) !== '' ? trim($module) : null;
    }
}

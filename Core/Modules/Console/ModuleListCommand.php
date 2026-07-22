<?php

namespace Core\Modules\Console;

use Core\Modules\Services\ModuleManager;
use Illuminate\Console\Command;

class ModuleListCommand extends Command
{
    protected $signature = 'module:list
                            {--profile= : Filter by profile (extension, integration, hybrid)}
                            {--installed : Only installed modules}
                            {--enabled : Only enabled modules}';

    protected $description = 'List discovered modules and their install/runtime status';

    public function handle(ModuleManager $modules): int
    {
        $profileFilter = $this->option('profile');
        $profileFilter = is_string($profileFilter) && trim($profileFilter) !== ''
            ? strtolower(trim($profileFilter))
            : null;

        $rows = [];

        foreach ($modules->discover(true) as $manifest) {
            if ($profileFilter !== null && $manifest->profile()->value !== $profileFilter) {
                continue;
            }

            $installation = $modules->installation($manifest->key);

            if ((bool) $this->option('installed') && $installation === null) {
                continue;
            }

            if ((bool) $this->option('enabled') && ! $modules->isEnabled($manifest->key)) {
                continue;
            }

            $rows[] = [
                $manifest->key,
                $manifest->name,
                $manifest->version,
                $manifest->profile()->value,
                implode(', ', $manifest->capabilities),
                $installation !== null ? 'yes' : 'no',
                $modules->isEnabled($manifest->key) ? 'yes' : 'no',
                $modules->isLoaded($manifest->key) ? 'yes' : 'no',
            ];
        }

        if ($rows === []) {
            $this->components->warn(__('No modules matched the current filters.'));

            return self::SUCCESS;
        }

        $this->table(
            ['Key', 'Label', 'Version', 'Profile', 'Capabilities', 'Installed', 'Enabled', 'Loaded'],
            $rows,
        );

        $this->newLine();
        $this->line(__('Total: :count module(s).', ['count' => count($rows)]));

        return self::SUCCESS;
    }
}

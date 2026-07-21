<?php

namespace Core\Modules\Services;

/**
 * @deprecated Use InstalledModuleRepository. Kept as a thin adapter for callers
 *             that only need enabled-key lookups.
 */
class ModuleStateRepository
{
    public function __construct(
        private readonly InstalledModuleRepository $installed,
    ) {
    }

    /**
     * @return list<string>
     */
    public function enabledKeys(): array
    {
        return $this->installed->enabledKeys();
    }

    public function isEnabled(string $key): bool
    {
        return $this->installed->isEnabled($key);
    }
}

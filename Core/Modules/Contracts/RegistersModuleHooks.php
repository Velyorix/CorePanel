<?php

namespace Core\Modules\Contracts;

use Core\Modules\Services\ModuleHookRegistry;

/**
 * Modules that register hook, filter, or event listeners at boot.
 */
interface RegistersModuleHooks
{
    public function registerModuleHooks(ModuleHookRegistry $hooks): void;
}

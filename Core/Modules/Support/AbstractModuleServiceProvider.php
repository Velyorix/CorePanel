<?php

namespace Core\Modules\Support;

use Illuminate\Support\ServiceProvider;

/**
 * Base ServiceProvider for module packages declared in module.json "providers".
 */
abstract class AbstractModuleServiceProvider extends ServiceProvider
{
    /**
     * Stable module key this provider belongs to.
     */
    abstract public function moduleKey(): string;
}

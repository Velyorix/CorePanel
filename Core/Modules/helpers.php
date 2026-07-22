<?php

use Core\Modules\Services\ModuleHookRegistry;

if (! function_exists('module_hooks')) {
    function module_hooks(): ModuleHookRegistry
    {
        return app(ModuleHookRegistry::class);
    }
}

if (! function_exists('module_hook')) {
    function module_hook(string $hook, mixed ...$arguments): void
    {
        module_hooks()->runHook($hook, ...$arguments);
    }
}

if (! function_exists('module_apply_filter')) {
    function module_apply_filter(string $filter, mixed $value, mixed ...$arguments): mixed
    {
        return module_hooks()->applyFilter($filter, $value, ...$arguments);
    }
}

if (! function_exists('module_dispatch_event')) {
    function module_dispatch_event(string $event, mixed ...$payload): void
    {
        module_hooks()->dispatchEvent($event, ...$payload);
    }
}

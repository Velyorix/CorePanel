<?php

namespace Core\Modules\Services;

use Illuminate\Support\Facades\Event;

/**
 * Forwards Laravel domain events to the module hook registry under string aliases.
 */
class ModuleEventBridge
{
    public function __construct(
        private readonly ModuleHookRegistry $hooks,
    ) {
    }

    public function register(): void
    {
        $aliases = config('corepanel.modules.hooks.laravel_events', []);

        if (! is_array($aliases)) {
            return;
        }

        foreach ($aliases as $alias => $eventClass) {
            if (! is_string($alias) || trim($alias) === '' || ! is_string($eventClass) || ! class_exists($eventClass)) {
                continue;
            }

            Event::listen($eventClass, function (object $event) use ($alias): void {
                $this->hooks->dispatchEvent($alias, $event);
            });
        }
    }
}

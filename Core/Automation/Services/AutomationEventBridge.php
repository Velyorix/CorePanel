<?php

namespace Core\Automation\Services;

use Illuminate\Support\Facades\Event;

/**
 * Forward Laravel domain events into the automation event bus.
 */
class AutomationEventBridge
{
    public function __construct(
        private readonly AutomationEventBus $bus,
        private readonly AutomationEventPayloadFactory $payloads,
    ) {
    }

    public function register(): void
    {
        if (! $this->bus->enabled()) {
            return;
        }

        $aliases = config('corepanel.automation.events', AutomationEventPayloadFactory::defaultDomainMap());

        if (! is_array($aliases)) {
            return;
        }

        foreach ($aliases as $alias => $eventClass) {
            if (! is_string($alias) || trim($alias) === '') {
                continue;
            }

            if (! is_string($eventClass) || ! class_exists($eventClass)) {
                continue;
            }

            Event::listen($eventClass, function (object $event) use ($alias): void {
                $this->bus->dispatch(
                    $alias,
                    $this->payloads->fromDomainEvent($alias, $event),
                );
            });
        }
    }
}

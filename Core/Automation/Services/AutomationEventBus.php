<?php

namespace Core\Automation\Services;

use Core\Automation\DataTransferObjects\AutomationEventContext;
use Throwable;

/**
 * Central automation event bus with dynamically registered listeners.
 */
class AutomationEventBus
{
    /**
     * @var array<string, list<array{id: string, callback: callable, priority: int}>>
     */
    private array $listeners = [];

    private int $sequence = 0;

    public function enabled(): bool
    {
        return (bool) config('corepanel.automation.enabled', true);
    }

    /**
     * Register a listener for an automation event alias.
     *
     * @param  callable(AutomationEventContext): void  $callback
     */
    public function listen(string $event, callable $callback, int $priority = 100): string
    {
        $event = trim($event);
        $id = 'automation_listener_'.(++$this->sequence);

        $this->listeners[$event] ??= [];
        $this->listeners[$event][] = [
            'id' => $id,
            'callback' => $callback,
            'priority' => $priority,
        ];

        return $id;
    }

    public function forget(string $listenerId): void
    {
        foreach ($this->listeners as $event => $entries) {
            $this->listeners[$event] = array_values(array_filter(
                $entries,
                static fn (array $listener): bool => $listener['id'] !== $listenerId,
            ));

            if ($this->listeners[$event] === []) {
                unset($this->listeners[$event]);
            }
        }
    }

    public function forgetEvent(string $event): void
    {
        unset($this->listeners[trim($event)]);
    }

    public function flush(): void
    {
        $this->listeners = [];
    }

    public function dispatch(string $event, AutomationEventContext|array $context = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        $event = trim($event);

        if ($event === '') {
            return;
        }

        $payload = $context instanceof AutomationEventContext
            ? $context
            : AutomationEventContext::make($event, $context);

        if ($payload->event !== $event) {
            $payload = AutomationEventContext::make(
                $event,
                $payload->data,
                $payload->source,
                $payload->occurredAt,
            );
        }

        foreach ($this->sorted($this->listeners[$event] ?? []) as $listener) {
            try {
                ($listener['callback'])($payload);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    public function hasListeners(string $event): bool
    {
        return ($this->listeners[trim($event)] ?? []) !== [];
    }

    public function listenerCount(string $event): int
    {
        return count($this->listeners[trim($event)] ?? []);
    }

    /**
     * @return list<string>
     */
    public function registeredEvents(): array
    {
        return array_keys($this->listeners);
    }

    /**
     * @param  list<array{id: string, callback: callable, priority: int}>  $listeners
     * @return list<array{id: string, callback: callable, priority: int}>
     */
    private function sorted(array $listeners): array
    {
        usort(
            $listeners,
            static fn (array $a, array $b): int => $a['priority'] <=> $b['priority'],
        );

        return $listeners;
    }
}

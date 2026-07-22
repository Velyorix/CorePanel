<?php

namespace Core\Modules\Services;

/**
 * Central registry for module extension hooks, filters, and named events.
 */
class ModuleHookRegistry
{
    /** @var array<string, list<array{id: string, callback: callable, priority: int, module: ?string}>> */
    private array $hooks = [];

    /** @var array<string, list<array{id: string, callback: callable, priority: int, module: ?string}>> */
    private array $filters = [];

    /** @var array<string, list<array{id: string, callback: callable, priority: int, module: ?string}>> */
    private array $events = [];

    private int $listenerSequence = 0;

    public function __construct(
        private readonly ModuleSandbox $sandbox,
    ) {
    }

    public function registerHook(string $hook, callable $callback, int $priority = 10, ?string $moduleKey = null): string
    {
        return $this->register($this->hooks, $hook, $callback, $priority, $moduleKey);
    }

    public function runHook(string $hook, mixed ...$arguments): void
    {
        foreach ($this->sorted($this->hooks[$hook] ?? []) as $listener) {
            $this->invoke($listener, $arguments);
        }
    }

    public function registerFilter(string $filter, callable $callback, int $priority = 10, ?string $moduleKey = null): string
    {
        return $this->register($this->filters, $filter, $callback, $priority, $moduleKey);
    }

    public function applyFilter(string $filter, mixed $value, mixed ...$arguments): mixed
    {
        foreach ($this->sorted($this->filters[$filter] ?? []) as $listener) {
            $value = $this->invoke($listener, $arguments, $value, isFilter: true);
        }

        return $value;
    }

    public function listenEvent(string $event, callable $callback, int $priority = 10, ?string $moduleKey = null): string
    {
        return $this->register($this->events, $event, $callback, $priority, $moduleKey);
    }

    public function dispatchEvent(string $event, mixed ...$payload): void
    {
        foreach ($this->sorted($this->events[$event] ?? []) as $listener) {
            $this->invoke($listener, $payload);
        }
    }

    public function hasHook(string $hook): bool
    {
        return ($this->hooks[$hook] ?? []) !== [];
    }

    public function hookCount(string $hook): int
    {
        return count($this->hooks[$hook] ?? []);
    }

    public function forgetModule(string $moduleKey): void
    {
        foreach (['hooks', 'filters', 'events'] as $bucket) {
            /** @var array<string, list<array{id: string, callback: callable, priority: int, module: ?string}>> $store */
            $store = &$this->{$bucket};

            foreach ($store as $name => $listeners) {
                $store[$name] = array_values(array_filter(
                    $listeners,
                    static fn (array $listener): bool => ($listener['module'] ?? null) !== $moduleKey,
                ));

                if ($store[$name] === []) {
                    unset($store[$name]);
                }
            }
        }
    }

    public function flush(): void
    {
        $this->hooks = [];
        $this->filters = [];
        $this->events = [];
    }

    /**
     * @param  array<string, list<array{id: string, callback: callable, priority: int, module: ?string}>>  $store
     */
    private function register(array &$store, string $name, callable $callback, int $priority, ?string $moduleKey): string
    {
        $id = 'listener_'.(++$this->listenerSequence);

        $store[$name] ??= [];
        $store[$name][] = [
            'id' => $id,
            'callback' => $callback,
            'priority' => $priority,
            'module' => $moduleKey,
        ];

        return $id;
    }

    /**
     * @param  list<array{id: string, callback: callable, priority: int, module: ?string}>  $listeners
     * @return list<array{id: string, callback: callable, priority: int, module: ?string}>
     */
    private function sorted(array $listeners): array
    {
        usort(
            $listeners,
            static fn (array $left, array $right): int => $left['priority'] <=> $right['priority']
                ?: strcmp($left['id'], $right['id']),
        );

        return $listeners;
    }

    /**
     * @param  list<mixed>  $arguments
     */
    private function invoke(array $listener, array $arguments, mixed $value = null, bool $isFilter = false): mixed
    {
        $callback = $listener['callback'];
        $moduleKey = $listener['module'] ?? null;

        $runner = function () use ($callback, $arguments, $value, $isFilter): mixed {
            if ($isFilter) {
                return $callback($value, ...$arguments);
            }

            $callback(...$arguments);

            return null;
        };

        if (is_string($moduleKey) && $moduleKey !== '') {
            return $this->sandbox->run($moduleKey, $runner);
        }

        return $runner();
    }
}

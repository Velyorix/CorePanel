<?php

namespace Core\Modules\Services;

/**
 * Tracks the active module execution context for sandbox enforcement.
 */
class ModuleSandbox
{
    /** @var list<string> */
    private array $stack = [];

    private int $bypassDepth = 0;

    public function enter(string $moduleKey): void
    {
        $this->stack[] = $moduleKey;
    }

    public function leave(): void
    {
        array_pop($this->stack);
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function run(string $moduleKey, callable $callback): mixed
    {
        $this->enter($moduleKey);

        try {
            return $callback();
        } finally {
            $this->leave();
        }
    }

    /**
     * Temporarily disable enforcement for Core-mediated operations.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function bypass(callable $callback): mixed
    {
        $this->bypassDepth++;

        try {
            return $callback();
        } finally {
            $this->bypassDepth = max(0, $this->bypassDepth - 1);
        }
    }

    public function currentModuleKey(): ?string
    {
        if ($this->stack === []) {
            return null;
        }

        return $this->stack[array_key_last($this->stack)];
    }

    public function isActive(): bool
    {
        return $this->currentModuleKey() !== null && $this->bypassDepth === 0 && $this->enabled();
    }

    public function enabled(): bool
    {
        return (bool) config('corepanel.modules.sandbox.enabled', true);
    }
}

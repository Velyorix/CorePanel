<?php

namespace Core\Automation\Concerns;

/**
 * Deterministic uniqueness for critical queued jobs (Laravel ShouldBeUnique).
 *
 * Implementing classes must define idempotencyKey() and implement ShouldBeUnique.
 */
trait IdempotentUniqueJob
{
    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return $this->idempotencyKey();
    }

    abstract public function idempotencyKey(): string;

    protected function configureUniqueFor(?int $seconds = null): void
    {
        $this->uniqueFor = max(1, $seconds ?? (int) config(
            'corepanel.automation.idempotency.unique_for_seconds',
            3600,
        ));
    }
}

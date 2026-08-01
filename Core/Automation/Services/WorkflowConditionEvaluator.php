<?php

namespace Core\Automation\Services;

use InvalidArgumentException;

/**
 * Evaluate workflow/rule conditions against a flat or nested data bag.
 *
 * Supported shapes:
 * - null / [] → always true
 * - {"all":[...]} / {"any":[...]}
 * - single clause {"field","operator","value"}
 */
class WorkflowConditionEvaluator
{
    /**
     * @param  array<string, mixed>|null  $conditions
     * @param  array<string, mixed>  $data
     */
    public function matches(?array $conditions, array $data): bool
    {
        if ($conditions === null || $conditions === []) {
            return true;
        }

        if (array_key_exists('all', $conditions) && is_array($conditions['all'])) {
            foreach ($conditions['all'] as $clause) {
                if (! is_array($clause) || ! $this->matchesClause($clause, $data)) {
                    return false;
                }
            }

            return true;
        }

        if (array_key_exists('any', $conditions) && is_array($conditions['any'])) {
            if ($conditions['any'] === []) {
                return true;
            }

            foreach ($conditions['any'] as $clause) {
                if (is_array($clause) && $this->matchesClause($clause, $data)) {
                    return true;
                }
            }

            return false;
        }

        return $this->matchesClause($conditions, $data);
    }

    /**
     * @param  array<string, mixed>  $clause
     * @param  array<string, mixed>  $data
     */
    private function matchesClause(array $clause, array $data): bool
    {
        $field = (string) ($clause['field'] ?? '');
        $operator = strtolower((string) ($clause['operator'] ?? 'eq'));
        $expected = $clause['value'] ?? null;
        $actual = data_get($data, $field);

        return match ($operator) {
            'eq', '=' => $this->equals($actual, $expected),
            'neq', '!=', '<>' => ! $this->equals($actual, $expected),
            'gt', '>' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'gte', '>=' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
            'lt', '<' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'lte', '<=' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
            'in' => is_array($expected) && $this->inArray($actual, $expected),
            'not_in' => is_array($expected) && ! $this->inArray($actual, $expected),
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            'exists' => $actual !== null,
            'empty' => blank($actual),
            'not_empty' => filled($actual),
            default => throw new InvalidArgumentException("Unsupported condition operator [{$operator}]."),
        };
    }

    private function equals(mixed $actual, mixed $expected): bool
    {
        if (is_bool($expected)) {
            return filter_var($actual, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === $expected;
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        return (string) $actual === (string) $expected;
    }

    /**
     * @param  list<mixed>  $expected
     */
    private function inArray(mixed $actual, array $expected): bool
    {
        foreach ($expected as $candidate) {
            if ($this->equals($actual, $candidate)) {
                return true;
            }
        }

        return false;
    }
}

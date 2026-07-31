<?php

namespace Core\Automation\Services;

use Core\Automation\Contracts\WorkflowActionHandler;
use Core\Automation\DataTransferObjects\WorkflowRunContext;
use InvalidArgumentException;

/**
 * Registry of workflow step action handlers (built-in + dynamically registered).
 */
class WorkflowActionRegistry
{
    /** @var array<string, WorkflowActionHandler|callable(array<string, mixed>, WorkflowRunContext): array<string, mixed>> */
    private array $handlers = [];

    public function register(string $type, WorkflowActionHandler|callable $handler): void
    {
        $type = trim($type);

        if ($type === '') {
            throw new InvalidArgumentException('Workflow action type cannot be empty.');
        }

        $this->handlers[$type] = $handler;
    }

    public function has(string $type): bool
    {
        return isset($this->handlers[trim($type)]);
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    public function run(array $step, WorkflowRunContext $context): array
    {
        $type = trim((string) ($step['type'] ?? ''));

        if ($type === '' || ! isset($this->handlers[$type])) {
            throw new InvalidArgumentException(
                $type === ''
                    ? 'Workflow step is missing a type.'
                    : "Unsupported workflow action type [{$type}].",
            );
        }

        $handler = $this->handlers[$type];

        if ($handler instanceof WorkflowActionHandler) {
            return $handler->handle($step, $context);
        }

        /** @var array<string, mixed> $result */
        $result = $handler($step, $context);

        return $result;
    }

    public function registerDefaults(): void
    {
        $this->register('noop', static fn (array $step, WorkflowRunContext $context): array => [
            'ok' => true,
        ]);

        $this->register('log', function (array $step, WorkflowRunContext $context): array {
            $message = (string) ($step['message'] ?? 'workflow.step');
            $logs = $context->get('_logs', []);
            if (! is_array($logs)) {
                $logs = [];
            }
            $logs[] = $message;
            $context->set('_logs', $logs);

            return [
                'logged' => $message,
            ];
        });

        $this->register('set', function (array $step, WorkflowRunContext $context): array {
            $key = (string) ($step['key'] ?? '');
            if ($key === '') {
                throw new InvalidArgumentException('Action [set] requires a key.');
            }

            $context->set($key, $step['value'] ?? null);

            return [
                'key' => $key,
                'value' => $step['value'] ?? null,
            ];
        });
    }
}

<?php

namespace Core\Automation\Services;

use Core\Automation\Contracts\RuleActionHandler;
use Core\Automation\DataTransferObjects\RuleRunContext;
use Core\Services\Models\Service;
use Core\Services\Services\ServiceControlService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Registry of rule action handlers (if → then).
 */
class RuleActionRegistry
{
    /** @var array<string, RuleActionHandler|callable(array<string, mixed>, RuleRunContext): array<string, mixed>> */
    private array $handlers = [];

    public function register(string $type, RuleActionHandler|callable $handler): void
    {
        $type = trim($type);

        if ($type === '') {
            throw new InvalidArgumentException('Rule action type cannot be empty.');
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
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    public function run(array $action, RuleRunContext $context): array
    {
        $type = trim((string) ($action['type'] ?? ''));

        if ($type === '' || ! isset($this->handlers[$type])) {
            throw new InvalidArgumentException(
                $type === ''
                    ? 'Rule action is missing a type.'
                    : "Unsupported rule action type [{$type}].",
            );
        }

        $handler = $this->handlers[$type];

        if ($handler instanceof RuleActionHandler) {
            return $handler->handle($action, $context);
        }

        /** @var array<string, mixed> $result */
        $result = $handler($action, $context);

        return $result;
    }

    public function registerDefaults(): void
    {
        $this->register('noop', static fn (array $action, RuleRunContext $context): array => [
            'ok' => true,
        ]);

        $this->register('log', function (array $action, RuleRunContext $context): array {
            $message = (string) ($action['message'] ?? 'rule.action');
            $logs = $context->get('_logs', []);
            if (! is_array($logs)) {
                $logs = [];
            }
            $logs[] = $message;
            $context->set('_logs', $logs);

            return ['logged' => $message];
        });

        $this->register('set', function (array $action, RuleRunContext $context): array {
            $key = (string) ($action['key'] ?? '');
            if ($key === '') {
                throw new InvalidArgumentException('Action [set] requires a key.');
            }

            $context->set($key, $action['value'] ?? null);

            return [
                'key' => $key,
                'value' => $action['value'] ?? null,
            ];
        });

        $this->register('suspend_service', function (array $action, RuleRunContext $context): array {
            $serviceId = (int) ($action['service_id'] ?? $context->get('service_id') ?? 0);

            if ($serviceId <= 0) {
                throw new RuntimeException('Action [suspend_service] requires service_id.');
            }

            $service = Service::query()->find($serviceId);

            if ($service === null) {
                throw new RuntimeException("Service [{$serviceId}] was not found.");
            }

            $service = app(ServiceControlService::class)->suspend($service);

            return [
                'service_id' => $service->id,
                'status' => $service->status?->value ?? $service->status,
            ];
        });
    }
}

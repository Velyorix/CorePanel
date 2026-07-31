<?php

namespace Core\Automation\Services;

use Illuminate\Support\Str;

/**
 * Builds deterministic idempotency keys for automation runs and queue jobs.
 */
class AutomationIdempotencyKey
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function forWorkflow(int|string $workflowId, string $event, array $data): string
    {
        return $this->compose('workflow', (string) $workflowId, $event, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function forRule(int|string $ruleId, string $event, array $data): string
    {
        return $this->compose('rule', (string) $ruleId, $event, $data);
    }

    /**
     * @param  array<string, scalar|null>  $parts
     */
    public function forJob(string $jobClass, array $parts = []): string
    {
        $normalized = ['job' => class_basename($jobClass)];

        foreach ($parts as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $normalized[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        ksort($normalized);

        return 'job:'.sha1(json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function fingerprint(array $data): string
    {
        $keys = config('corepanel.automation.idempotency.fingerprint_keys', [
            'invoice_id',
            'service_id',
            'ticket_id',
            'node_id',
            'client_id',
            'order_id',
        ]);

        if (! is_array($keys)) {
            $keys = [];
        }

        $parts = [];

        foreach ($keys as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $value = data_get($data, $key);

            if ($value === null || $value === '') {
                continue;
            }

            $parts[$key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        if ($parts === []) {
            return 'none';
        }

        ksort($parts);

        return sha1(json_encode($parts, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function hasEntityFingerprint(array $data): bool
    {
        return $this->fingerprint($data) !== 'none';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function compose(string $scope, string $id, string $event, array $data): string
    {
        $fingerprint = $this->fingerprint($data);

        if ($fingerprint === 'none') {
            return implode(':', [$scope, $id, $event, 'once', (string) Str::uuid()]);
        }

        return implode(':', [$scope, $id, $event, $fingerprint]);
    }
}

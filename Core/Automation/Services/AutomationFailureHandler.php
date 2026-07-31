<?php

namespace Core\Automation\Services;

use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Models\AutomationLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Decide whether a failed automation run should retry or fall back.
 */
class AutomationFailureHandler
{
    public function maxAttempts(): int
    {
        return max(1, (int) config('corepanel.automation.retry.max_attempts', 4));
    }

    /**
     * @return list<int>
     */
    public function backoffSeconds(): array
    {
        $raw = config('corepanel.automation.retry.backoff_seconds', [0, 300, 1800, 3600]);

        if (! is_array($raw) || $raw === []) {
            return [0, 300, 1800, 3600];
        }

        return array_values(array_map(
            static fn (mixed $value): int => max(0, (int) $value),
            $raw,
        ));
    }

    public function canRetry(AutomationLog $log): bool
    {
        return $log->attempt < $this->maxAttempts();
    }

    public function delayAfterAttempt(int $failedAttempt): int
    {
        $backoff = $this->backoffSeconds();
        $index = max(0, $failedAttempt - 1);

        if ($index >= count($backoff)) {
            return $backoff[array_key_last($backoff)] ?? 0;
        }

        return $backoff[$index];
    }

    /**
     * @param  array<string, mixed>|null  $fallback
     */
    public function handle(AutomationLog $log, Throwable $exception, ?array $fallback = null): AutomationLog
    {
        $partial = is_array($log->result) ? $log->result : [];

        if ($this->canRetry($log)) {
            $delay = $this->delayAfterAttempt($log->attempt);

            $log->forceFill([
                'status' => AutomationLogStatus::Retrying,
                'error_message' => $exception->getMessage(),
                'next_retry_at' => Carbon::now()->addSeconds($delay),
                'finished_at' => now(),
                'result' => array_merge($partial, [
                    'retry' => [
                        'attempt' => $log->attempt,
                        'next_attempt' => $log->attempt + 1,
                        'delay_seconds' => $delay,
                    ],
                ]),
            ])->save();

            Log::info('automation.retry.scheduled', [
                'log_id' => $log->id,
                'attempt' => $log->attempt,
                'next_retry_at' => $log->next_retry_at?->toIso8601String(),
            ]);

            return $log->fresh() ?? $log;
        }

        return $this->finalizeWithoutRetry($log, $exception, $fallback, $partial);
    }

    /**
     * @param  array<string, mixed>|null  $fallback
     * @param  array<string, mixed>  $partial
     */
    public function finalizeWithoutRetry(
        AutomationLog $log,
        Throwable $exception,
        ?array $fallback,
        array $partial = [],
    ): AutomationLog {
        if ($fallback !== null && $fallback !== [] && isset($fallback['type'])) {
            $log->forceFill([
                'status' => AutomationLogStatus::Fallback,
                'error_message' => $exception->getMessage(),
                'next_retry_at' => null,
                'finished_at' => null,
                'result' => array_merge($partial, [
                    'fallback_pending' => true,
                    'primary_error' => $exception->getMessage(),
                ]),
            ])->save();

            return $log->fresh() ?? $log;
        }

        $log->forceFill([
            'status' => AutomationLogStatus::Failed,
            'error_message' => $exception->getMessage(),
            'next_retry_at' => null,
            'finished_at' => now(),
            'result' => $partial,
        ])->save();

        return $log->fresh() ?? $log;
    }

    /**
     * Mark a successful fallback execution.
     *
     * @param  array<string, mixed>  $fallbackResult
     */
    public function markFallbackSucceeded(AutomationLog $log, array $fallbackResult, Throwable $primary): AutomationLog
    {
        $partial = is_array($log->result) ? $log->result : [];
        unset($partial['fallback_pending']);

        $log->forceFill([
            'status' => AutomationLogStatus::Fallback,
            'error_message' => $primary->getMessage(),
            'next_retry_at' => null,
            'finished_at' => now(),
            'result' => array_merge($partial, [
                'fallback' => $fallbackResult,
                'primary_error' => $primary->getMessage(),
            ]),
        ])->save();

        Log::info('automation.fallback.succeeded', [
            'log_id' => $log->id,
            'fallback_type' => $fallbackResult['type'] ?? null,
        ]);

        return $log->fresh() ?? $log;
    }

    /**
     * Mark a failed fallback execution as permanently failed.
     *
     * @param  array<string, mixed>  $partial
     */
    public function markFallbackFailed(AutomationLog $log, Throwable $primary, Throwable $fallbackError, array $partial = []): AutomationLog
    {
        $log->forceFill([
            'status' => AutomationLogStatus::Failed,
            'error_message' => $fallbackError->getMessage(),
            'next_retry_at' => null,
            'finished_at' => now(),
            'result' => array_merge($partial, [
                'primary_error' => $primary->getMessage(),
                'fallback_error' => $fallbackError->getMessage(),
            ]),
        ])->save();

        Log::warning('automation.fallback.failed', [
            'log_id' => $log->id,
            'primary' => $primary->getMessage(),
            'fallback' => $fallbackError->getMessage(),
        ]);

        return $log->fresh() ?? $log;
    }
}

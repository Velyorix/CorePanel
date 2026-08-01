<?php

namespace Core\Automation\Services;

use Core\Automation\DataTransferObjects\IdempotencyClaim;
use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Models\AutomationLog;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prevents duplicate automation executions for the same idempotency key.
 */
class AutomationIdempotencyGuard
{
    public function __construct(
        private readonly AutomationIdempotencyKey $keys,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('corepanel.automation.idempotency.enabled', true);
    }

    /**
     * @return list<AutomationLogStatus>
     */
    public function blockingStatuses(): array
    {
        return [
            AutomationLogStatus::Pending,
            AutomationLogStatus::Running,
            AutomationLogStatus::Succeeded,
            AutomationLogStatus::Retrying,
            AutomationLogStatus::Fallback,
        ];
    }

    public function findBlocking(string $key): ?AutomationLog
    {
        return AutomationLog::query()
            ->where('idempotency_key', $key)
            ->whereIn('status', array_map(
                static fn (AutomationLogStatus $status): string => $status->value,
                $this->blockingStatuses(),
            ))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Claim a key under a cache lock and create the running log, or return an existing blocker.
     *
     * @param  callable(): AutomationLog  $factory
     */
    public function claimOrExisting(string $key, callable $factory): IdempotencyClaim
    {
        if (! $this->enabled()) {
            return new IdempotencyClaim($factory(), true);
        }

        $existing = $this->findBlocking($key);

        if ($existing !== null) {
            $this->logHit($key, $existing);

            return new IdempotencyClaim($existing, false);
        }

        $lockSeconds = max(1, (int) config('corepanel.automation.idempotency.lock_seconds', 30));
        $lock = Cache::lock($this->lockName($key), $lockSeconds);
        $acquired = false;

        try {
            $acquired = $lock->get();

            return DB::transaction(function () use ($key, $factory): IdempotencyClaim {
                $existing = $this->findBlocking($key);

                if ($existing !== null) {
                    $this->logHit($key, $existing);

                    return new IdempotencyClaim($existing, false);
                }

                $this->releaseFailedKeyOccupants($key);

                try {
                    return new IdempotencyClaim($factory(), true);
                } catch (Throwable $exception) {
                    $existing = $this->findBlocking($key);

                    if ($existing !== null) {
                        $this->logHit($key, $existing);

                        return new IdempotencyClaim($existing, false);
                    }

                    throw $exception;
                }
            });
        } finally {
            if ($acquired) {
                $this->releaseLock($lock);
            }
        }
    }

    /**
     * Free the deterministic key after a permanent failure so a later event can re-run.
     */
    public function releaseAfterFailure(AutomationLog $log): void
    {
        if (! $this->enabled()) {
            return;
        }

        $key = $log->idempotency_key;

        if (! is_string($key) || $key === '' || str_starts_with($key, 'released:')) {
            return;
        }

        $log->forceFill([
            'idempotency_key' => 'released:'.$log->id.':'.$key,
        ])->save();
    }

    public function keys(): AutomationIdempotencyKey
    {
        return $this->keys;
    }

    private function lockName(string $key): string
    {
        return 'automation:idempotency:'.sha1($key);
    }

    private function releaseFailedKeyOccupants(string $key): void
    {
        $failed = AutomationLog::query()
            ->where('idempotency_key', $key)
            ->where('status', AutomationLogStatus::Failed)
            ->get();

        foreach ($failed as $log) {
            $this->releaseAfterFailure($log);
        }
    }

    private function logHit(string $key, AutomationLog $log): void
    {
        Log::info('automation.idempotency.hit', [
            'key' => $key,
            'log_id' => $log->id,
            'status' => $log->status?->value ?? $log->status,
        ]);
    }

    private function releaseLock(Lock $lock): void
    {
        try {
            $lock->release();
        } catch (Throwable) {
            // Lock may already have expired.
        }
    }
}

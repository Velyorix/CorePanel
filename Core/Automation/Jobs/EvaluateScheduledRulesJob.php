<?php

namespace Core\Automation\Jobs;

use Core\Automation\Concerns\IdempotentUniqueJob;
use Core\Automation\Services\AutomationIdempotencyKey;
use Core\Automation\Services\RulesEngine;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateScheduledRulesJob implements ShouldBeUnique, ShouldQueue
{
    use IdempotentUniqueJob;
    use Queueable;

    public function __construct()
    {
        $this->configureUniqueFor();
    }

    public function idempotencyKey(): string
    {
        return app(AutomationIdempotencyKey::class)->forJob(self::class, [
            'slot' => now()->format('YmdHi'),
        ]);
    }

    public function handle(RulesEngine $rules): void
    {
        if (! (bool) config('corepanel.automation.enabled', true)) {
            return;
        }

        $rules->evaluate([
            'source' => 'scheduler',
            'evaluated_at' => now()->toIso8601String(),
        ]);
    }
}

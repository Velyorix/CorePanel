<?php

namespace Core\Automation\Jobs;

use Core\Automation\Services\RulesEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateScheduledRulesJob implements ShouldQueue
{
    use Queueable;

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

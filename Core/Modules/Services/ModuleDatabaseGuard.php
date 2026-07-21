<?php

namespace Core\Modules\Services;

use Core\Modules\Exceptions\ModuleSandboxViolationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;

class ModuleDatabaseGuard
{
    private bool $registered = false;

    public function __construct(
        private readonly ModuleSandbox $sandbox,
        private readonly ModuleTableAccessPolicy $policy,
    ) {
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        Event::listen(QueryExecuted::class, function (QueryExecuted $event): void {
            $this->inspect($event->sql);
        });

        $this->registered = true;
    }

    public function inspect(string $sql): void
    {
        if (! $this->sandbox->isActive()) {
            return;
        }

        $moduleKey = $this->sandbox->currentModuleKey();

        if ($moduleKey === null) {
            return;
        }

        $this->policy->assertQueryAllowed($moduleKey, $sql);
    }
}

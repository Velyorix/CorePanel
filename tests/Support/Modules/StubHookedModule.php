<?php

namespace Tests\Support\Modules;

use Core\Modules\Contracts\RegistersModuleHooks;
use Core\Modules\Services\ModuleHookRegistry;
use Core\Modules\Support\AbstractModule;

class StubHookedModule extends AbstractModule implements RegistersModuleHooks
{
    /** @var list<string> */
    public array $hookCalls = [];

    public function registerModuleHooks(ModuleHookRegistry $hooks): void
    {
        $hooks->registerHook(
            'demo.extension',
            function (): void {
                $this->hookCalls[] = 'interface';
            },
            moduleKey: $this->key(),
        );
    }

    public function boot(): void
    {
        $this->host()->registerHook('demo.boot', function (): void {
            $this->hookCalls[] = 'boot';
        });
    }
}

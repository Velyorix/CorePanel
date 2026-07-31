<?php

namespace Tests\Support\Modules;

use Core\Modules\Support\AbstractModuleServiceProvider;

class StubExampleServiceProvider extends AbstractModuleServiceProvider
{
    public static bool $registered = false;

    public static bool $booted = false;

    public function moduleKey(): string
    {
        return 'provider_demo';
    }

    public function register(): void
    {
        self::$registered = true;

        $this->app->instance('modules.provider_demo.flag', 'registered');
        $this->app->singleton('modules.provider_demo.service', fn (): object => (object) [
            'module' => $this->moduleKey(),
            'ready' => true,
        ]);
    }

    public function boot(): void
    {
        self::$booted = true;
    }
}

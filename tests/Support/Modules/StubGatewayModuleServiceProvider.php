<?php

namespace Tests\Support\Modules;

use Core\Modules\Support\AbstractModuleServiceProvider;
use Tests\Support\Billing\FakePaymentGateway;

class StubGatewayModuleServiceProvider extends AbstractModuleServiceProvider
{
    public static bool $booted = false;

    public function moduleKey(): string
    {
        return 'gateway_demo';
    }

    public function register(): void
    {
        $this->app->singleton(FakePaymentGateway::class, fn (): FakePaymentGateway => new FakePaymentGateway(
            key: 'provider_fake',
            label: 'Provider Fake Gateway',
        ));
    }

    public function boot(): void
    {
        self::$booted = true;

        $this->registerPaymentGateway($this->app->make(FakePaymentGateway::class));
    }
}

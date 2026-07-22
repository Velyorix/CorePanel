<?php

namespace Core\Billing\Services;

use Core\Billing\Contracts\PaymentGateway;
use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Exceptions\ModuleBootstrapException;
use Core\Modules\Services\ModuleSandbox;
use Illuminate\Contracts\Foundation\Application;

/**
 * Injects payment gateways declared by loaded modules.
 */
class PaymentGatewayInjector
{
    /** @var array<string, list<string>> */
    private array $moduleGateways = [];

    public function __construct(
        private readonly Application $app,
        private readonly GatewayManager $gateways,
        private readonly ModuleSandbox $sandbox,
    ) {
    }

    /**
     * Register payment gateway classes declared in module.json "gateways".
     *
     * @return list<string> Newly registered gateway class names
     */
    public function registerFromModule(ModuleManifest $manifest): array
    {
        $registeredNow = [];

        foreach ($manifest->gateways as $gatewayClass) {
            if ($this->isModuleGatewayRegistered($manifest->key, $gatewayClass)) {
                continue;
            }

            if (! class_exists($gatewayClass)) {
                throw ModuleBootstrapException::gatewayMissing($manifest->key, $gatewayClass);
            }

            if (! is_subclass_of($gatewayClass, PaymentGateway::class)) {
                throw ModuleBootstrapException::invalidGateway($manifest->key, $gatewayClass);
            }

            $this->sandbox->run($manifest->key, function () use ($gatewayClass): void {
                /** @var PaymentGateway $gateway */
                $gateway = $this->app->make($gatewayClass);
                $this->gateways->register($gateway);
            });

            $this->moduleGateways[$manifest->key] ??= [];
            $this->moduleGateways[$manifest->key][] = $gatewayClass;
            $registeredNow[] = $gatewayClass;
        }

        return $registeredNow;
    }

    public function isModuleGatewayRegistered(string $moduleKey, string $gatewayClass): bool
    {
        return in_array($gatewayClass, $this->moduleGateways[$moduleKey] ?? [], true);
    }

    /**
     * @return list<string>
     */
    public function gatewaysFor(string $moduleKey): array
    {
        return $this->moduleGateways[$moduleKey] ?? [];
    }

    public function forgetModule(string $moduleKey): void
    {
        unset($this->moduleGateways[$moduleKey]);
    }
}

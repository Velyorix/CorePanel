<?php

namespace Core\Providers\Services;

use Core\Providers\Contracts\NodeProviderInterface;
use Core\Providers\Contracts\PaymentGatewayInterface;
use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\Exceptions\UnknownProviderException;

/**
 * In-memory registry of module providers (server/node/payment).
 */
class ProviderRegistry
{
    /** @var array<string, ServerProviderInterface> */
    private array $serverProviders = [];

    /** @var array<string, NodeProviderInterface> */
    private array $nodeProviders = [];

    /** @var array<string, PaymentGatewayInterface> */
    private array $paymentGateways = [];

    public function registerServer(ServerProviderInterface $provider): void
    {
        $this->serverProviders[$provider->key()] = $provider;
    }

    public function hasServer(string $key): bool
    {
        return isset($this->serverProviders[$key]);
    }

    public function server(string $key): ServerProviderInterface
    {
        if (! isset($this->serverProviders[$key])) {
            throw UnknownProviderException::forServer($key);
        }

        return $this->serverProviders[$key];
    }

    /**
     * @return list<ServerProviderInterface>
     */
    public function allServers(): array
    {
        return array_values($this->serverProviders);
    }

    /**
     * @return list<string>
     */
    public function serverKeys(): array
    {
        return array_keys($this->serverProviders);
    }

    public function registerNode(NodeProviderInterface $provider): void
    {
        $this->nodeProviders[$provider->key()] = $provider;
    }

    public function hasNode(string $key): bool
    {
        return isset($this->nodeProviders[$key]);
    }

    public function node(string $key): NodeProviderInterface
    {
        if (! isset($this->nodeProviders[$key])) {
            throw UnknownProviderException::forNode($key);
        }

        return $this->nodeProviders[$key];
    }

    /**
     * @return list<NodeProviderInterface>
     */
    public function allNodes(): array
    {
        return array_values($this->nodeProviders);
    }

    /**
     * @return list<string>
     */
    public function nodeKeys(): array
    {
        return array_keys($this->nodeProviders);
    }

    /**
     * @return list<string>
     */
    public function provisioningModuleKeys(): array
    {
        return array_values(array_unique([
            ...$this->serverKeys(),
            ...$this->nodeKeys(),
        ]));
    }

    public function registerPaymentGateway(PaymentGatewayInterface $provider): void
    {
        $this->paymentGateways[$provider->key()] = $provider;
    }

    public function hasPaymentGateway(string $key): bool
    {
        return isset($this->paymentGateways[$key]);
    }

    public function paymentGateway(string $key): PaymentGatewayInterface
    {
        if (! isset($this->paymentGateways[$key])) {
            throw UnknownProviderException::forPaymentGateway($key);
        }

        return $this->paymentGateways[$key];
    }

    /**
     * @return list<PaymentGatewayInterface>
     */
    public function allPaymentGateways(): array
    {
        return array_values($this->paymentGateways);
    }

    /**
     * @return list<string>
     */
    public function paymentGatewayKeys(): array
    {
        return array_keys($this->paymentGateways);
    }

    public function flush(): void
    {
        $this->serverProviders = [];
        $this->nodeProviders = [];
        $this->paymentGateways = [];
    }
}

<?php

namespace Core\Providers\DataTransferObjects;

use InvalidArgumentException;

final readonly class NodeConnectionRequest
{
    /**
     * @param  array<string, mixed>|null  $credentials
     * @param  array<string, mixed>|null  $config
     */
    public function __construct(
        public string $hostname,
        public ?int $id = null,
        public ?string $module = null,
        public ?string $name = null,
        public ?string $ipAddress = null,
        public ?string $apiUrl = null,
        public ?array $credentials = null,
        public ?array $config = null,
        public ?int $maxServices = null,
    ) {
        if ($this->hostname === '') {
            throw new InvalidArgumentException('Node hostname is required.');
        }
    }

    /**
     * @param  array{
     *     id?: int|null,
     *     module?: string|null,
     *     name?: string|null,
     *     hostname: string,
     *     ip_address?: string|null,
     *     api_url?: string|null,
     *     credentials?: array<string, mixed>|null,
     *     config?: array<string, mixed>|null,
     *     max_services?: int|null,
     * }  $node
     */
    public static function fromArray(array $node): self
    {
        $hostname = trim((string) ($node['hostname'] ?? ''));

        if ($hostname === '') {
            throw new InvalidArgumentException('Node hostname is required.');
        }

        $credentials = $node['credentials'] ?? null;
        if ($credentials !== null && ! is_array($credentials)) {
            throw new InvalidArgumentException('Node credentials must be an array.');
        }

        $config = $node['config'] ?? null;
        if ($config !== null && ! is_array($config)) {
            throw new InvalidArgumentException('Node config must be an array.');
        }

        $maxServices = $node['max_services'] ?? null;

        return new self(
            hostname: $hostname,
            id: isset($node['id']) ? (int) $node['id'] : null,
            module: isset($node['module']) ? trim((string) $node['module']) : null,
            name: isset($node['name']) ? trim((string) $node['name']) : null,
            ipAddress: isset($node['ip_address']) ? trim((string) $node['ip_address']) : null,
            apiUrl: isset($node['api_url']) ? trim((string) $node['api_url']) : null,
            credentials: $credentials,
            config: $config,
            maxServices: $maxServices !== null ? (int) $maxServices : null,
        );
    }

    /**
     * @return array{
     *     id?: int|null,
     *     module?: string|null,
     *     name?: string|null,
     *     hostname: string,
     *     ip_address?: string|null,
     *     api_url?: string|null,
     *     credentials?: array<string, mixed>|null,
     *     config?: array<string, mixed>|null,
     *     max_services?: int|null,
     * }
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'module' => $this->module,
            'name' => $this->name,
            'hostname' => $this->hostname,
            'ip_address' => $this->ipAddress,
            'api_url' => $this->apiUrl,
            'credentials' => $this->credentials,
            'config' => $this->config,
            'max_services' => $this->maxServices,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}

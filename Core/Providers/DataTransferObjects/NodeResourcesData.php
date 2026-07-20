<?php

namespace Core\Providers\DataTransferObjects;

final readonly class NodeResourcesData
{
    public function __construct(
        public ?int $maxServices = null,
        public ?int $currentServices = null,
        public ?float $cpuUsage = null,
        public ?float $ramUsage = null,
        public ?float $diskUsage = null,
        public ?float $networkIn = null,
        public ?float $networkOut = null,
        public ?float $loadAverage = null,
        public ?bool $capacityAvailable = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $resources
     */
    public static function fromArray(array $resources): self
    {
        return new self(
            maxServices: isset($resources['max_services']) ? (int) $resources['max_services'] : null,
            currentServices: isset($resources['current_services']) ? (int) $resources['current_services'] : null,
            cpuUsage: isset($resources['cpu_usage']) ? (float) $resources['cpu_usage'] : null,
            ramUsage: isset($resources['ram_usage']) ? (float) $resources['ram_usage'] : null,
            diskUsage: isset($resources['disk_usage']) ? (float) $resources['disk_usage'] : null,
            networkIn: isset($resources['network_in']) ? (float) $resources['network_in'] : null,
            networkOut: isset($resources['network_out']) ? (float) $resources['network_out'] : null,
            loadAverage: isset($resources['load_average']) ? (float) $resources['load_average'] : null,
            capacityAvailable: isset($resources['capacity_available'])
                ? (bool) $resources['capacity_available']
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'max_services' => $this->maxServices,
            'current_services' => $this->currentServices,
            'cpu_usage' => $this->cpuUsage,
            'ram_usage' => $this->ramUsage,
            'disk_usage' => $this->diskUsage,
            'network_in' => $this->networkIn,
            'network_out' => $this->networkOut,
            'load_average' => $this->loadAverage,
            'capacity_available' => $this->capacityAvailable,
        ], static fn (mixed $value): bool => $value !== null);
    }
}

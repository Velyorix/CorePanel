<?php

namespace Core\Nodes\DataTransferObjects;

use Core\Nodes\Models\Node;

final readonly class NodeCapacityUsage
{
    public function __construct(
        public int $cpuCores = 0,
        public int $ramMb = 0,
        public int $diskGb = 0,
        public int $services = 0,
        public float $bandwidthInMbps = 0.0,
        public float $bandwidthOutMbps = 0.0,
        public ?float $loadAverage = null,
        public ?bool $capacityAvailable = null,
        public ?string $syncedAt = null,
        public ?string $source = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    public static function fromArray(array $usage): self
    {
        return new self(
            cpuCores: max(0, (int) ($usage['cpu_cores'] ?? 0)),
            ramMb: max(0, (int) ($usage['ram_mb'] ?? 0)),
            diskGb: max(0, (int) ($usage['disk_gb'] ?? 0)),
            services: max(0, (int) ($usage['services'] ?? 0)),
            bandwidthInMbps: max(0.0, (float) ($usage['bandwidth_in_mbps'] ?? 0)),
            bandwidthOutMbps: max(0.0, (float) ($usage['bandwidth_out_mbps'] ?? 0)),
            loadAverage: isset($usage['load_average']) ? (float) $usage['load_average'] : null,
            capacityAvailable: isset($usage['capacity_available'])
                ? (bool) $usage['capacity_available']
                : null,
            syncedAt: isset($usage['synced_at']) ? (string) $usage['synced_at'] : null,
            source: isset($usage['source']) ? (string) $usage['source'] : null,
        );
    }

    /**
     * @return array{cpu_cores: int, ram_mb: int, disk_gb: int}
     */
    public function allocatedResources(): array
    {
        return [
            'cpu_cores' => $this->cpuCores,
            'ram_mb' => $this->ramMb,
            'disk_gb' => $this->diskGb,
        ];
    }

    public static function fromNode(Node $node): self
    {
        $capacity = is_array($node->config['capacity'] ?? null)
            ? $node->config['capacity']
            : [];

        $usage = is_array($capacity['usage'] ?? null)
            ? $capacity['usage']
            : [];

        if ($usage === [] && is_array($capacity['allocated'] ?? null)) {
            $usage = $capacity['allocated'];
        }

        if (! isset($usage['synced_at']) && isset($capacity['synced_at'])) {
            $usage['synced_at'] = $capacity['synced_at'];
        }

        if (! isset($usage['source']) && isset($capacity['source'])) {
            $usage['source'] = $capacity['source'];
        }

        if (! isset($usage['capacity_available']) && array_key_exists('available', $capacity)) {
            $usage['capacity_available'] = $capacity['available'];
        }

        return self::fromArray($usage);
    }

    public function peakBandwidthMbps(): float
    {
        return max($this->bandwidthInMbps, $this->bandwidthOutMbps);
    }
}

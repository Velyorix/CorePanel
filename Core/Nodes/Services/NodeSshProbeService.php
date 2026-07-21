<?php

namespace Core\Nodes\Services;

use Core\Nodes\DataTransferObjects\NodeSshConnection;
use Core\Nodes\Models\Node;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\NodeResourcesData;
use Core\Providers\DataTransferObjects\NodeResourcesResponse;
use Core\Services\Enums\ServiceStatus;
use RuntimeException;
use Throwable;

class NodeSshProbeService
{
    public function __construct(
        private readonly NodeSshClient $client,
    ) {
    }

    public function supports(NodeConnectionRequest $node): bool
    {
        return NodeSshConnection::tryFromNodeRequest($node) !== null;
    }

    public function testConnection(NodeConnectionRequest $node): NodeOperationResponse
    {
        $connection = $this->resolveConnection($node);

        if ($connection === null) {
            return NodeOperationResponse::failed(__('SSH credentials are incomplete. Provide a username and password or private key.'));
        }

        try {
            $this->client->ping($connection);

            return NodeOperationResponse::success(
                message: __('SSH connection successful.'),
                payload: [
                    'transport' => 'ssh',
                    'host' => $connection->host,
                    'port' => $connection->port,
                    'username' => $connection->username,
                ],
            );
        } catch (Throwable $exception) {
            return NodeOperationResponse::failed(
                message: $exception->getMessage(),
                payload: [
                    'transport' => 'ssh',
                    'host' => $connection->host,
                    'port' => $connection->port,
                ],
            );
        }
    }

    public function collectResources(NodeConnectionRequest $node): NodeResourcesResponse
    {
        $connection = $this->resolveConnection($node);

        if ($connection === null) {
            return NodeResourcesResponse::failed(__('SSH credentials are incomplete. Provide a username and password or private key.'));
        }

        try {
            $output = $this->client->run($connection, $this->metricsScript());
            $metrics = $this->parseMetricsOutput($output);
            $currentServices = $this->currentServicesCount($node);
            $maxServices = $node->maxServices;
            $capacityAvailable = $this->capacityAvailable($node, $metrics, $currentServices);

            return NodeResourcesResponse::success(
                new NodeResourcesData(
                    maxServices: $maxServices,
                    currentServices: $currentServices,
                    cpuUsage: $metrics['cpu'],
                    ramUsage: $metrics['ram_mb'],
                    diskUsage: $metrics['disk_gb'],
                    networkIn: $metrics['network_in_mbps'],
                    networkOut: $metrics['network_out_mbps'],
                    loadAverage: $metrics['load'],
                    capacityAvailable: $capacityAvailable,
                ),
                payload: [
                    'transport' => 'ssh',
                    'host' => $connection->host,
                    'port' => $connection->port,
                    'uptime_seconds' => $metrics['uptime_seconds'],
                ],
            );
        } catch (Throwable $exception) {
            return NodeResourcesResponse::failed(
                message: $exception->getMessage(),
                payload: [
                    'transport' => 'ssh',
                    'host' => $connection->host,
                    'port' => $connection->port,
                ],
            );
        }
    }

    private function resolveConnection(NodeConnectionRequest $node): ?NodeSshConnection
    {
        return NodeSshConnection::tryFromNodeRequest($node);
    }

    private function metricsScript(): string
    {
        $script = <<<'BASH'
set -e
read -r load _ _ _ _ < /proc/loadavg
mem_total=$(awk '/MemTotal/ {print $2; exit}' /proc/meminfo)
mem_avail=$(awk '/MemAvailable/ {print $2; exit}' /proc/meminfo)
if [ -z "$mem_avail" ] || [ "$mem_avail" -eq 0 ] 2>/dev/null; then
  mem_avail=$(awk '/MemFree/ {free=$2} /Buffers/ {buf=$2} /^Cached:/ {cached=$2} END {print free+buf+cached}' /proc/meminfo)
fi
ram_mb=$(( (mem_total - mem_avail) / 1024 ))
disk_gb=$(df -BG / 2>/dev/null | awk 'NR==2 {gsub(/G/,"",$3); print $3}')
if [ -z "$disk_gb" ]; then
  disk_gb=$(df -m / 2>/dev/null | awk 'NR==2 {print int($3/1024)}')
fi
uptime_seconds=$(cut -d. -f1 /proc/uptime)
read -r _ u1 n1 s1 i1 iw1 ir1 sf1 sg1 _ < /proc/stat
idle1=$((i1 + iw1))
total1=$((u1 + n1 + s1 + i1 + iw1 + ir1 + sf1 + sg1))
sleep 1
read -r _ u2 n2 s2 i2 iw2 ir2 sf2 sg2 _ < /proc/stat
idle2=$((i2 + iw2))
total2=$((u2 + n2 + s2 + i2 + iw2 + ir2 + sf2 + sg2))
total_delta=$((total2 - total1))
idle_delta=$((idle2 - idle1))
if [ "$total_delta" -gt 0 ]; then
  cpu_pct=$(( (1000 * (total_delta - idle_delta)) / (10 * total_delta) ))
else
  cpu_pct=0
fi
rx1=$(awk 'NR>2 && $1 !~ /lo:/ {gsub(":","",$1); rx+=$2} END {print rx+0}' /proc/net/dev)
tx1=$(awk 'NR>2 && $1 !~ /lo:/ {gsub(":","",$1); tx+=$10} END {print tx+0}' /proc/net/dev)
sleep 1
rx2=$(awk 'NR>2 && $1 !~ /lo:/ {gsub(":","",$1); rx+=$2} END {print rx+0}' /proc/net/dev)
tx2=$(awk 'NR>2 && $1 !~ /lo:/ {gsub(":","",$1); tx+=$10} END {print tx+0}' /proc/net/dev)
net_in_mbps=$(( (rx2 - rx1) * 8 / 1000000 ))
net_out_mbps=$(( (tx2 - tx1) * 8 / 1000000 ))
printf 'LOAD=%s\n' "$load"
printf 'RAM_MB=%s\n' "$ram_mb"
printf 'DISK_GB=%s\n' "$disk_gb"
printf 'CPU=%s\n' "$cpu_pct"
printf 'NET_IN=%s\n' "$net_in_mbps"
printf 'NET_OUT=%s\n' "$net_out_mbps"
printf 'UPTIME=%s\n' "$uptime_seconds"
BASH;

        return 'echo '.escapeshellarg(base64_encode($script)).' | base64 -d | bash';
    }

    /**
     * @return array{
     *     load: float,
     *     ram_mb: float,
     *     disk_gb: float,
     *     cpu: float,
     *     network_in_mbps: float,
     *     network_out_mbps: float,
     *     uptime_seconds: int
     * }
     */
    private function parseMetricsOutput(string $output): array
    {
        $values = [
            'LOAD' => 0.0,
            'RAM_MB' => 0.0,
            'DISK_GB' => 0.0,
            'CPU' => 0.0,
            'NET_IN' => 0.0,
            'NET_OUT' => 0.0,
            'UPTIME' => 0,
        ];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, null);

            if ($key === null || $value === null || ! array_key_exists($key, $values)) {
                continue;
            }

            $values[$key] = is_numeric($value) ? $value : 0;
        }

        return $this->normalizeMetrics($values);
    }

    /**
     * @param  array{
     *     LOAD: float|int|string,
     *     RAM_MB: float|int|string,
     *     DISK_GB: float|int|string,
     *     CPU: float|int|string,
     *     NET_IN: float|int|string,
     *     NET_OUT: float|int|string,
     *     UPTIME: float|int|string
     * }  $values
     * @return array{
     *     load: float,
     *     ram_mb: float,
     *     disk_gb: float,
     *     cpu: float,
     *     network_in_mbps: float,
     *     network_out_mbps: float,
     *     uptime_seconds: int
     * }
     */
    private function normalizeMetrics(array $values): array
    {
        if ((int) $values['UPTIME'] <= 0 && (float) $values['RAM_MB'] <= 0.0) {
            throw new RuntimeException(__('SSH metrics probe returned empty data.'));
        }

        return [
            'load' => round((float) $values['LOAD'], 2),
            'ram_mb' => round(max(0.0, (float) $values['RAM_MB']), 2),
            'disk_gb' => round(max(0.0, (float) $values['DISK_GB']), 2),
            'cpu' => round(max(0.0, min(100.0, (float) $values['CPU'])), 2),
            'network_in_mbps' => round(max(0.0, (float) $values['NET_IN']), 2),
            'network_out_mbps' => round(max(0.0, (float) $values['NET_OUT']), 2),
            'uptime_seconds' => max(0, (int) $values['UPTIME']),
        ];
    }

    private function currentServicesCount(NodeConnectionRequest $node): int
    {
        if ($node->id === null) {
            return 0;
        }

        return Node::query()
            ->whereKey($node->id)
            ->withCount([
                'services as allocated_services_count' => function ($builder): void {
                    $builder->whereNotIn('status', [
                        ServiceStatus::Terminated->value,
                        ServiceStatus::Cancelled->value,
                    ]);
                },
            ])
            ->value('allocated_services_count') ?? 0;
    }

    /**
     * @param  array{
     *     load: float,
     *     ram_mb: float,
     *     disk_gb: float,
     *     cpu: float,
     *     network_in_mbps: float,
     *     network_out_mbps: float,
     *     uptime_seconds: int
     * }  $metrics
     */
    private function capacityAvailable(NodeConnectionRequest $node, array $metrics, int $currentServices): bool
    {
        if ($node->maxServices !== null && $currentServices >= $node->maxServices) {
            return false;
        }

        if ($node->maxRamMb !== null && $metrics['ram_mb'] >= $node->maxRamMb) {
            return false;
        }

        if ($node->maxDiskGb !== null && $metrics['disk_gb'] >= $node->maxDiskGb) {
            return false;
        }

        return true;
    }
}

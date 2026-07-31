<?php

namespace Core\Nodes\Services;

use Core\Nodes\DataTransferObjects\NodeLoadBalanceCandidate;
use Core\Nodes\Enums\NodeHealthState;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeHealthCheck;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class NodeLoadBalancingService
{
    /**
     * @param  Collection<int, Node>  $candidates
     */
    public function select(Collection $candidates, string $scope, callable $utilizationScore): ?Node
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        if (! $this->enabled()) {
            return $this->pickLowestUtilization($candidates, $utilizationScore);
        }

        $band = $this->utilizationBand($candidates, $utilizationScore);
        $prepared = $this->prepareCandidates($band, $utilizationScore);

        if ($prepared->isEmpty()) {
            return $this->pickLowestUtilization($candidates, $utilizationScore);
        }

        if ($prepared->count() === 1) {
            return $prepared->first()->node;
        }

        return $this->weightedRoundRobin($prepared, $scope)?->node
            ?? $this->pickLowestUtilization($candidates, $utilizationScore);
    }

    public function enabled(): bool
    {
        return (bool) config('corepanel.nodes.allocation.load_balancing.enabled', true);
    }

    /**
     * @param  Collection<int, Node>  $candidates
     */
    private function utilizationBand(Collection $candidates, callable $utilizationScore): Collection
    {
        $minimum = $candidates
            ->map(static fn (Node $node): float => (float) $utilizationScore($node))
            ->min();

        if ($minimum === null) {
            return $candidates->values();
        }

        $margin = max(0.0, (float) config('corepanel.nodes.allocation.load_balancing.utilization_band', 0.15));

        return $candidates
            ->filter(static fn (Node $node): bool => (float) $utilizationScore($node) <= ($minimum + $margin))
            ->values();
    }

    /**
     * @param  Collection<int, Node>  $candidates
     * @return Collection<int, NodeLoadBalanceCandidate>
     */
    private function prepareCandidates(Collection $candidates, callable $utilizationScore): Collection
    {
        $reliability = $this->reliabilityFactors($candidates);

        return $candidates
            ->map(function (Node $node) use ($utilizationScore, $reliability): ?NodeLoadBalanceCandidate {
                $utilization = (float) $utilizationScore($node);
                $effectiveWeight = $this->effectiveWeight(
                    node: $node,
                    utilization: $utilization,
                    reliability: $reliability[(int) $node->id] ?? $this->unknownReliabilityFactor(),
                );

                if ($effectiveWeight <= 0) {
                    return null;
                }

                return new NodeLoadBalanceCandidate(
                    node: $node,
                    utilization: $utilization,
                    effectiveWeight: $effectiveWeight,
                );
            })
            ->filter(static fn (?NodeLoadBalanceCandidate $candidate): bool => $candidate !== null)
            ->values();
    }

    private function effectiveWeight(Node $node, float $utilization, float $reliability): int
    {
        $baseWeight = max(1, min(1000, $this->baseWeight($node)));
        $uptimeFactor = $this->uptimeFactor($node);
        $performanceFactor = max(
            0.05,
            1.0 - min(max($utilization, 0.0), 1.0),
        );

        $weight = (int) round($baseWeight * $uptimeFactor * $performanceFactor * $reliability);

        return max(1, $weight);
    }

    private function baseWeight(Node $node): int
    {
        $config = is_array($node->config['allocation'] ?? null)
            ? $node->config['allocation']
            : [];

        if (isset($config['weight'])) {
            return max(1, (int) $config['weight']);
        }

        return max(1, (int) config('corepanel.nodes.allocation.load_balancing.default_weight', 100));
    }

    private function uptimeFactor(Node $node): float
    {
        $factors = config('corepanel.nodes.allocation.load_balancing.uptime_factors', []);

        return match ($node->healthState()) {
            NodeHealthState::Online => (float) ($factors['online'] ?? 1.0),
            NodeHealthState::Degraded => (float) ($factors['degraded'] ?? 0.5),
            NodeHealthState::Unknown => (float) ($factors['unknown'] ?? 0.85),
            default => (float) ($factors['offline'] ?? 0.1),
        };
    }

    /**
     * @param  Collection<int, Node>  $candidates
     * @return array<int, float>
     */
    private function reliabilityFactors(Collection $candidates): array
    {
        if (! (bool) config('corepanel.nodes.allocation.load_balancing.use_reliability_history', true)) {
            return [];
        }

        $hours = max(1, (int) config('corepanel.nodes.allocation.load_balancing.reliability_hours', 24));
        $since = now()->subHours($hours);
        $nodeIds = $candidates
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $checks = NodeHealthCheck::query()
            ->whereIn('node_id', $nodeIds)
            ->where('checked_at', '>=', $since)
            ->where('state', '!=', NodeHealthState::Skipped->value)
            ->orderBy('node_id')
            ->get()
            ->groupBy('node_id');

        $factors = [];

        foreach ($nodeIds as $nodeId) {
            $nodeChecks = $checks->get($nodeId, collect());

            if ($nodeChecks->isEmpty()) {
                $factors[$nodeId] = $this->unknownReliabilityFactor();

                continue;
            }

            $score = 0.0;

            foreach ($nodeChecks as $check) {
                $score += match ($check->state) {
                    NodeHealthState::Online => 1.0,
                    NodeHealthState::Degraded => 0.5,
                    default => 0.0,
                };
            }

            $factors[$nodeId] = max(0.1, min(1.0, $score / $nodeChecks->count()));
        }

        return $factors;
    }

    private function unknownReliabilityFactor(): float
    {
        return max(0.1, min(1.0, (float) config(
            'corepanel.nodes.allocation.load_balancing.unknown_reliability_factor',
            0.85,
        )));
    }

    /**
     * @param  Collection<int, NodeLoadBalanceCandidate>  $candidates
     */
    private function weightedRoundRobin(Collection $candidates, string $scope): ?NodeLoadBalanceCandidate
    {
        $cacheKey = $this->stateCacheKey($scope);
        /** @var array<int, int> $currentWeights */
        $currentWeights = Cache::get($cacheKey, []);
        $selected = null;
        $selectedCurrent = PHP_INT_MIN;

        foreach ($candidates as $candidate) {
            $nodeId = (int) $candidate->node->id;
            $current = ($currentWeights[$nodeId] ?? 0) + $candidate->effectiveWeight;
            $currentWeights[$nodeId] = $current;

            if ($current > $selectedCurrent) {
                $selected = $candidate;
                $selectedCurrent = $current;
            }
        }

        if ($selected === null) {
            return null;
        }

        $totalWeight = $candidates->sum(static fn (NodeLoadBalanceCandidate $candidate): int => $candidate->effectiveWeight);
        $selectedId = (int) $selected->node->id;
        $currentWeights[$selectedId] -= $totalWeight;

        Cache::put(
            $cacheKey,
            $currentWeights,
            now()->addSeconds(max(60, (int) config('corepanel.nodes.allocation.load_balancing.state_ttl_seconds', 3600))),
        );

        return $selected;
    }

    private function stateCacheKey(string $scope): string
    {
        $prefix = (string) config('corepanel.nodes.allocation.load_balancing.cache_prefix', 'nodes.load_balancing');

        return $prefix.'.'.$scope;
    }

    /**
     * @param  Collection<int, Node>  $candidates
     */
    private function pickLowestUtilization(Collection $candidates, callable $utilizationScore): ?Node
    {
        return $candidates
            ->sort(function (Node $left, Node $right) use ($utilizationScore): int {
                return (float) $utilizationScore($left) <=> (float) $utilizationScore($right)
                    ?: $left->allocatedServicesCount() <=> $right->allocatedServicesCount()
                    ?: $left->sort_order <=> $right->sort_order
                    ?: $left->id <=> $right->id;
            })
            ->first();
    }
}

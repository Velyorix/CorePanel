<?php

namespace Core\Nodes\Services;

use Core\Nodes\Exceptions\NodeSecurityException;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Symfony\Component\HttpFoundation\IpUtils;

class NodeIpWhitelistService
{
    public function enabled(): bool
    {
        return (bool) config('corepanel.nodes.security.ip_whitelist.enabled', false);
    }

    public function assertAllowed(NodeConnectionRequest $request): void
    {
        if (! $this->enabled()) {
            return;
        }

        $allowed = $this->allowedEntries($request);

        if ($allowed === []) {
            throw NodeSecurityException::ipWhitelistEmpty();
        }

        $targets = $this->resolveTargetIps($request);

        if ($targets === []) {
            throw NodeSecurityException::ipWhitelistUnresolved();
        }

        foreach ($targets as $ip) {
            if ($this->allowsPrivateAddresses() && $this->isPrivateAddress($ip)) {
                continue;
            }

            if (! $this->matchesAny($ip, $allowed)) {
                throw NodeSecurityException::ipNotWhitelisted($ip);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function allowedEntries(NodeConnectionRequest $request): array
    {
        $global = config('corepanel.nodes.security.ip_whitelist.allowed', []);
        $config = is_array($request->config) ? $request->config : [];
        $security = is_array($config['security'] ?? null) ? $config['security'] : [];
        $nodeAllowed = $security['allowed_ips'] ?? [];

        return array_values(array_unique(array_merge(
            $this->normalizeList(is_array($global) ? $global : []),
            $this->normalizeList(is_array($nodeAllowed) ? $nodeAllowed : []),
        )));
    }

    /**
     * @return list<string>
     */
    private function resolveTargetIps(NodeConnectionRequest $request): array
    {
        $hosts = [];

        foreach ([
            trim((string) ($request->ipAddress ?? '')),
            trim($request->hostname),
            $this->hostFromUrl(trim((string) ($request->apiUrl ?? ''))),
        ] as $host) {
            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        $ips = [];

        foreach (array_unique($hosts) as $host) {
            $ip = $this->resolveIp($host);

            if ($ip !== null) {
                $ips[] = $ip;
            }
        }

        return array_values(array_unique($ips));
    }

    private function resolveIp(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        if (! $this->shouldResolveHostnames()) {
            return null;
        }

        $resolved = gethostbyname($host);

        if ($resolved === $host || ! filter_var($resolved, FILTER_VALIDATE_IP)) {
            return null;
        }

        return $resolved;
    }

    private function hostFromUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return '';
        }

        return trim((string) ($parts['host'] ?? ''));
    }

    /**
     * @param  list<string>  $allowed
     */
    private function matchesAny(string $ip, array $allowed): bool
    {
        foreach ($allowed as $entry) {
            if (IpUtils::checkIp($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<mixed>  $entries
     * @return list<string>
     */
    private function normalizeList(array $entries): array
    {
        $normalized = [];

        foreach ($entries as $entry) {
            $value = trim((string) $entry);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    private function allowsPrivateAddresses(): bool
    {
        return (bool) config('corepanel.nodes.security.ip_whitelist.allow_private', true);
    }

    private function shouldResolveHostnames(): bool
    {
        return (bool) config('corepanel.nodes.security.ip_whitelist.resolve_hostnames', true);
    }

    private function isPrivateAddress(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }
}

<?php

namespace Core\Nodes\DataTransferObjects;

use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use InvalidArgumentException;

final readonly class NodeSshConnection
{
    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        public ?string $password = null,
        public ?string $privateKey = null,
    ) {
        if ($this->host === '') {
            throw new InvalidArgumentException('SSH host is required.');
        }

        if ($this->username === '') {
            throw new InvalidArgumentException('SSH username is required.');
        }

        if ($this->password === null && $this->privateKey === null) {
            throw new InvalidArgumentException('SSH password or private key is required.');
        }
    }

    public static function tryFromNodeRequest(NodeConnectionRequest $node): ?self
    {
        $credentials = is_array($node->credentials) ? $node->credentials : [];

        $username = trim((string) ($credentials['ssh_username'] ?? ''));

        if ($username === '') {
            return null;
        }

        $password = trim((string) ($credentials['ssh_password'] ?? ''));
        $privateKey = trim((string) ($credentials['ssh_private_key'] ?? ''));

        if ($password === '' && $privateKey === '') {
            return null;
        }

        $host = self::resolveHost($node);

        if ($host === '') {
            return null;
        }

        $config = is_array($node->config) ? $node->config : [];
        $sshConfig = is_array($config['ssh'] ?? null) ? $config['ssh'] : [];
        $port = (int) ($sshConfig['port'] ?? config('corepanel.nodes.ssh.port', 22));

        return new self(
            host: $host,
            port: max(1, min($port, 65535)),
            username: $username,
            password: $password !== '' ? $password : null,
            privateKey: $privateKey !== '' ? self::normalizePrivateKey($privateKey) : null,
        );
    }

    public function fingerprintKey(): string
    {
        return strtolower($this->host).':'.$this->port;
    }

    public function endpointLabel(): string
    {
        return $this->host.':'.$this->port;
    }

    private static function resolveHost(NodeConnectionRequest $node): string
    {
        foreach ([
            trim((string) ($node->ipAddress ?? '')),
            trim($node->hostname),
            self::hostFromUrl(trim((string) ($node->apiUrl ?? ''))),
        ] as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private static function hostFromUrl(string $url): string
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

    private static function normalizePrivateKey(string $privateKey): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($privateKey));

        return str_ends_with($normalized, "\n") ? $normalized : $normalized."\n";
    }
}

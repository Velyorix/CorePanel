<?php

namespace Core\Nodes\Services;

use Core\Nodes\DataTransferObjects\NodeSshConnection;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\UnableToConnectException;
use phpseclib3\Net\SSH2;
use RuntimeException;
use Throwable;

class NodeSshClient
{
    public function __construct(
        private readonly NodeSshKnownHostsStore $knownHosts,
    ) {
    }

    public function run(NodeSshConnection $connection, string $command): string
    {
        $ssh = $this->connect($connection);

        try {
            $output = $ssh->exec($command);

            if ($output === false) {
                throw new RuntimeException('SSH command execution failed.');
            }

            return trim((string) $output);
        } finally {
            $ssh->disconnect();
        }
    }

    public function ping(NodeSshConnection $connection): void
    {
        $ssh = $this->connect($connection);

        try {
            $output = $ssh->exec('echo corepanel-ssh-ok');

            if (! str_contains((string) $output, 'corepanel-ssh-ok')) {
                throw new RuntimeException('SSH health probe did not return the expected response.');
            }
        } finally {
            $ssh->disconnect();
        }
    }

    private function connect(NodeSshConnection $connection): SSH2
    {
        $timeout = max(1, (int) config('corepanel.nodes.ssh.timeout', 10));

        try {
            $ssh = new SSH2($connection->host, $connection->port, $timeout);

            $this->verifyHostKey($connection, $ssh);

            if (! $this->authenticate($connection, $ssh)) {
                throw new RuntimeException(__('SSH authentication failed for :endpoint.', [
                    'endpoint' => $connection->endpointLabel(),
                ]));
            }

            return $ssh;
        } catch (UnableToConnectException $exception) {
            throw new RuntimeException(
                __('Unable to reach :endpoint. Verify the IP address, SSH port, and firewall rules from the panel server.', [
                    'endpoint' => $connection->endpointLabel(),
                ]),
                previous: $exception,
            );
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException(
                __('SSH connection to :endpoint failed: :message', [
                    'endpoint' => $connection->endpointLabel(),
                    'message' => $exception->getMessage(),
                ]),
                previous: $exception,
            );
        }
    }

    private function authenticate(NodeSshConnection $connection, SSH2 $ssh): bool
    {
        if ($connection->privateKey !== null) {
            try {
                $passphrase = $connection->password;
                $key = PublicKeyLoader::load(
                    $connection->privateKey,
                    $passphrase !== null && $passphrase !== '' ? $passphrase : false,
                );

                return $ssh->login($connection->username, $key);
            } catch (Throwable) {
                return false;
            }
        }

        return $ssh->login($connection->username, (string) $connection->password);
    }

    private function verifyHostKey(NodeSshConnection $connection, SSH2 $ssh): void
    {
        $policy = (string) config('corepanel.nodes.ssh.host_key_policy', 'accept_new');

        if ($policy === 'none') {
            return;
        }

        $serverKey = $ssh->getServerPublicHostKey();

        if (! is_string($serverKey) || $serverKey === '') {
            throw new RuntimeException(__('Unable to read the SSH host key from :endpoint.', [
                'endpoint' => $connection->endpointLabel(),
            ]));
        }

        $fingerprint = hash('sha256', $serverKey);
        $hostKey = $connection->fingerprintKey();
        $known = $this->knownHosts->lookup($hostKey);

        if ($known === null) {
            if ($policy !== 'accept_new') {
                throw new RuntimeException(__('Unknown SSH host key for :endpoint.', [
                    'endpoint' => $connection->endpointLabel(),
                ]));
            }

            $this->knownHosts->remember($hostKey, $fingerprint);

            return;
        }

        if (! hash_equals($known, $fingerprint)) {
            throw new RuntimeException(__('SSH host key mismatch for :endpoint.', [
                'endpoint' => $connection->endpointLabel(),
            ]));
        }
    }
}

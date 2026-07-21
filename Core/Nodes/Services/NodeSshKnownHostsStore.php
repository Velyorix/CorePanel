<?php

namespace Core\Nodes\Services;

use Illuminate\Support\Facades\File;

class NodeSshKnownHostsStore
{
    public function path(): string
    {
        return (string) config('corepanel.nodes.ssh.known_hosts_path', storage_path('app/nodes/ssh_known_hosts'));
    }

    public function lookup(string $hostKey): ?string
    {
        $entries = $this->entries();

        return $entries[$hostKey] ?? null;
    }

    public function remember(string $hostKey, string $fingerprint): void
    {
        $entries = $this->entries();
        $entries[$hostKey] = $fingerprint;

        $this->persist($entries);
    }

    /**
     * @return array<string, string>
     */
    private function entries(): array
    {
        $path = $this->path();

        if (! File::exists($path)) {
            return [];
        }

        $contents = trim((string) File::get($path));

        if ($contents === '') {
            return [];
        }

        $entries = [];
        $lines = preg_split('/\R/', $contents) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $fingerprint] = array_pad(explode(' ', $line, 2), 2, null);

            if ($key === null || $fingerprint === null || $fingerprint === '') {
                continue;
            }

            $entries[$key] = $fingerprint;
        }

        return $entries;
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function persist(array $entries): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        ksort($entries);

        $lines = [];

        foreach ($entries as $key => $fingerprint) {
            $lines[] = $key.' '.$fingerprint;
        }

        File::put($path, implode(PHP_EOL, $lines).( $lines !== [] ? PHP_EOL : ''));
    }
}

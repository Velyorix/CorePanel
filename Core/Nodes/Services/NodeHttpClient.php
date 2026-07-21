<?php

namespace Core\Nodes\Services;

use Core\Nodes\Exceptions\NodeSecurityException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class NodeHttpClient
{
    public function assertSecureApiUrl(?string $apiUrl): void
    {
        $apiUrl = trim((string) $apiUrl);

        if ($apiUrl === '' || ! $this->tlsRequired()) {
            return;
        }

        $scheme = strtolower((string) parse_url($apiUrl, PHP_URL_SCHEME));

        if ($scheme !== 'https') {
            throw NodeSecurityException::tlsRequired($apiUrl);
        }
    }

    public function tlsRequired(): bool
    {
        return (bool) config('corepanel.nodes.security.tls.required', env('APP_ENV') !== 'local');
    }

    public function pendingRequest(): PendingRequest
    {
        $client = Http::acceptJson()
            ->asJson()
            ->timeout(max(1, (int) config('corepanel.nodes.security.tls.timeout_seconds', 15)));

        $caBundle = $this->normalizedCaBundlePath();

        if ($caBundle !== null) {
            return $client->withOptions(['verify' => $caBundle]);
        }

        if ($this->shouldSkipSslVerification()) {
            return $client->withoutVerifying();
        }

        return $client;
    }

    private function shouldSkipSslVerification(): bool
    {
        return ! (bool) config('corepanel.nodes.security.tls.verify_ssl', env('APP_ENV') !== 'local');
    }

    private function normalizedCaBundlePath(): ?string
    {
        $path = trim((string) config('corepanel.nodes.security.tls.ca_bundle', ''));

        if ($path === '' || ! is_file($path)) {
            return null;
        }

        return $path;
    }
}

<?php

namespace Core\License\Services;

use Core\License\DataTransferObjects\LicenseValidationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class CorePanelOrgClient
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function validateLicense(
        string $licenseKey,
        string $instanceId,
        ?string $instanceLabel = null,
        ?string $domain = null,
        array $metadata = [],
    ): LicenseValidationResult {
        $payload = array_filter([
            'license_key' => $licenseKey,
            'instance_id' => $instanceId,
            'instance_label' => filled($instanceLabel) ? $instanceLabel : null,
            'domain' => filled($domain) ? $domain : null,
            'metadata' => $metadata === [] ? null : $metadata,
        ], static fn (mixed $value): bool => $value !== null);

        try {
            $response = $this->httpClient()
                ->post($this->endpoint('licenses/validate'), $payload);
        } catch (ConnectionException $exception) {
            return LicenseValidationResult::requestFailed($exception->getMessage());
        } catch (Throwable $exception) {
            // Some PHP/cURL builds surface SSL failures outside ConnectionException.
            if ($this->isSslCertificateError($exception->getMessage())) {
                return LicenseValidationResult::requestFailed($exception->getMessage());
            }

            throw $exception;
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        $retryAfterHeader = is_numeric($response->header('Retry-After'))
            ? (int) $response->header('Retry-After')
            : null;

        return LicenseValidationResult::fromResponse($json, $response->status(), $retryAfterHeader);
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('corepanel.org.api_url'), '/').'/'.ltrim($path, '/');
    }

    private function httpClient(): PendingRequest
    {
        $client = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('corepanel.org.timeout_seconds', 10));

        $caBundle = $this->normalizedCaBundlePath();

        if ($caBundle !== null) {
            return $client->withOptions(['verify' => $caBundle]);
        }

        // Local Windows/dev often lacks a usable CA bundle — never block activation on SSL.
        if ($this->shouldSkipSslVerification()) {
            return $client->withoutVerifying();
        }

        return $client;
    }

    private function shouldSkipSslVerification(): bool
    {
        if (app()->environment('local', 'testing')) {
            return true;
        }

        return ! (bool) config('corepanel.org.verify_ssl', true);
    }

    private function normalizedCaBundlePath(): ?string
    {
        $caBundle = config('corepanel.org.ca_bundle');

        if (! is_string($caBundle) || trim($caBundle) === '') {
            return null;
        }

        // Dotenv can turn Windows backslashes into escapes (\t, \n, ...).
        $path = str_replace('\\', '/', trim($caBundle));

        if (! is_file($path)) {
            return null;
        }

        return $path;
    }

    private function isSslCertificateError(string $message): bool
    {
        return str_contains($message, 'SSL certificate problem')
            || str_contains($message, 'unable to get local issuer certificate')
            || str_contains($message, 'cURL error 60');
    }
}

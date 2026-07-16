<?php

namespace Core\License\Services;

use Core\License\DataTransferObjects\LicenseValidationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

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
            'instance_label' => $instanceLabel,
            'domain' => $domain,
            'metadata' => $metadata === [] ? null : $metadata,
        ], static fn (mixed $value): bool => $value !== null);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('corepanel.org.timeout_seconds', 10))
                ->post($this->endpoint('licenses/validate'), $payload);
        } catch (ConnectionException $exception) {
            return LicenseValidationResult::requestFailed($exception->getMessage());
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return LicenseValidationResult::fromResponse($json, $response->status());
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('corepanel.org.api_url'), '/').'/'.ltrim($path, '/');
    }
}


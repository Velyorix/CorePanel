<?php

namespace Core\License\Services;

use Core\License\DataTransferObjects\LicenseValidationResult;
use Core\License\DataTransferObjects\LicenseValidationState;
use Core\License\Models\LicenseActivation;
use Illuminate\Support\Facades\Cache;

class LicenseValidationService
{
    public function __construct(
        private readonly CorePanelOrgClient $corePanelOrgClient,
        private readonly LicenseSettings $licenseSettings,
    ) {
    }

    public function validate(bool $forceRefresh = false): LicenseValidationState
    {
        $licenseKey = $this->licenseSettings->licenseKey();
        $instanceId = $this->licenseSettings->instanceId();

        if (blank($licenseKey) || blank($instanceId)) {
            return new LicenseValidationState(
                isValid: false,
                inGracePeriod: false,
                status: 'missing_configuration',
                reason: 'missing_configuration',
                message: __('License key or instance id is not configured.'),
                source: 'local',
            );
        }

        $cacheKey = $this->cacheKey((string) $instanceId);

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return LicenseValidationState::fromCachePayload($cached);
            }
        }

        $result = $this->corePanelOrgClient->validateLicense(
            licenseKey: (string) $licenseKey,
            instanceId: (string) $instanceId,
            instanceLabel: (string) config('corepanel.instance.label'),
            domain: (string) config('corepanel.instance.domain'),
            metadata: [
                'cms_version' => (string) config('corepanel.version'),
                'php_version' => PHP_VERSION,
            ],
        );

        if ($result->valid) {
            $this->persistActivation((string) $instanceId, $result);

            $state = new LicenseValidationState(
                isValid: true,
                inGracePeriod: false,
                status: (string) ($result->license['status'] ?? 'active'),
                reason: null,
                message: null,
                source: 'remote',
            );

            $this->putCache($cacheKey, $state);

            return $state;
        }

        if ($result->reason === 'request_failed' || $result->statusCode === 429) {
            $graceState = $this->graceState((string) $instanceId, $result->message);
            $this->putCache($cacheKey, $graceState);

            return $graceState;
        }

        $state = new LicenseValidationState(
            isValid: false,
            inGracePeriod: false,
            status: (string) ($result->license['status'] ?? 'invalid'),
            reason: $result->reason,
            message: $result->message,
            source: 'remote',
        );

        $this->putCache($cacheKey, $state);

        return $state;
    }

    public function forgetCache(): void
    {
        $instanceId = $this->licenseSettings->instanceId();
        if (blank($instanceId)) {
            return;
        }

        Cache::forget($this->cacheKey((string) $instanceId));
    }

    private function graceState(string $instanceId, ?string $errorMessage): LicenseValidationState
    {
        $activation = LicenseActivation::query()
            ->where('instance_id', $instanceId)
            ->first();

        if ($activation !== null
            && $activation->last_validated_at !== null
            && $activation->last_validated_at->addHours($this->gracePeriodHours())->isFuture()
        ) {
            return new LicenseValidationState(
                isValid: true,
                inGracePeriod: true,
                status: 'grace',
                reason: 'grace_period',
                message: $errorMessage,
                source: 'grace',
            );
        }

        return new LicenseValidationState(
            isValid: false,
            inGracePeriod: false,
            status: 'invalid',
            reason: 'request_failed',
            message: $errorMessage,
            source: 'remote',
        );
    }

    private function persistActivation(string $instanceId, LicenseValidationResult $result): void
    {
        LicenseActivation::query()->updateOrCreate(
            ['instance_id' => $instanceId],
            [
                'license_remote_id' => $result->license['id'] ?? null,
                'status' => $result->license['status'] ?? 'active',
                'instance_label' => config('corepanel.instance.label'),
                'domain' => config('corepanel.instance.domain'),
                'product_type' => $result->license['product_type'] ?? null,
                'expires_at' => $result->license['expires_at'] ?? null,
                'last_validated_at' => now(),
                'last_seen_at' => $result->activation['last_seen_at'] ?? now(),
                'entitlements' => $result->entitlements,
                'metadata' => $result->raw,
                'updated_at' => now(),
            ],
        );
    }

    private function cacheKey(string $instanceId): string
    {
        return 'corepanel.license.validation.'.sha1($instanceId);
    }

    private function putCache(string $cacheKey, LicenseValidationState $state): void
    {
        Cache::put(
            $cacheKey,
            $state->toCachePayload(),
            now()->addHours($this->cacheTtlHours()),
        );
    }

    private function cacheTtlHours(): int
    {
        return max(1, (int) config('corepanel.license.validation_cache_hours', 12));
    }

    private function gracePeriodHours(): int
    {
        return max(1, (int) config('corepanel.license.grace_period_hours', 72));
    }
}


<?php

namespace Tests\Support;

use Core\License\DataTransferObjects\LicenseValidationState;
use Core\License\Services\LicenseSettings;
use Illuminate\Support\Facades\Cache;

trait ConfiguresLicense
{
    protected function configureValidLicense(bool $inGracePeriod = false): void
    {
        $instanceId = '550e8400-e29b-41d4-a716-446655440000';

        app(LicenseSettings::class)->setLicenseKey('CP-TEST-DEFAULT-LICENSE');
        app(LicenseSettings::class)->setInstanceId($instanceId);

        $state = new LicenseValidationState(
            isValid: true,
            inGracePeriod: $inGracePeriod,
            status: $inGracePeriod ? 'grace' : 'active',
            reason: $inGracePeriod ? 'grace_period' : null,
            message: $inGracePeriod ? 'CorePanel.org unreachable' : null,
            source: 'test',
        );

        Cache::put(
            'corepanel.license.validation.'.sha1($instanceId),
            $state->toCachePayload(),
            now()->addHour(),
        );
    }

    protected function clearLicenseConfiguration(): void
    {
        app(LicenseSettings::class);

        Cache::flush();
    }
}

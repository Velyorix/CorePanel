<?php

namespace Core\License\Services;

use Core\Settings\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class LicenseSettings
{
    private const LICENSE_KEY = 'license.key';
    private const INSTANCE_ID = 'license.instance_id';

    public function setLicenseKey(string $licenseKey): void
    {
        $this->putEncrypted(self::LICENSE_KEY, $licenseKey);
    }

    public function licenseKey(): ?string
    {
        return $this->getEncrypted(self::LICENSE_KEY);
    }

    public function setInstanceId(string $instanceId): void
    {
        $this->putEncrypted(self::INSTANCE_ID, $instanceId);
    }

    public function instanceId(): ?string
    {
        return $this->getEncrypted(self::INSTANCE_ID);
    }

    public function hasLicenseKey(): bool
    {
        return filled($this->licenseKey());
    }

    public function hasInstanceId(): bool
    {
        return filled($this->instanceId());
    }

    private function putEncrypted(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => Crypt::encryptString($value),
                'type' => 'encrypted',
                'autoload' => false,
                'updated_at' => now(),
            ],
        );
    }

    private function getEncrypted(string $key): ?string
    {
        $setting = Setting::query()->where('key', $key)->first();

        if ($setting === null || blank($setting->value)) {
            return null;
        }

        try {
            return Crypt::decryptString($setting->value);
        } catch (DecryptException) {
            return null;
        }
    }
}


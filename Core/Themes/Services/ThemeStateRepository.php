<?php

namespace Core\Themes\Services;

use Core\Settings\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Persists the globally active theme key.
 */
class ThemeStateRepository
{
    public const ACTIVE_KEY = 'themes.active';

    public function activeKey(): ?string
    {
        if (! $this->ready()) {
            return null;
        }

        $value = Setting::query()->where('key', self::ACTIVE_KEY)->value('value');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    public function setActiveKey(string $key): void
    {
        Setting::query()->updateOrCreate(
            ['key' => self::ACTIVE_KEY],
            [
                'value' => trim($key),
                'type' => 'string',
                'autoload' => true,
                'updated_at' => now(),
            ],
        );
    }

    public function clearActiveKey(): void
    {
        if (! $this->ready()) {
            return;
        }

        Setting::query()->where('key', self::ACTIVE_KEY)->delete();
    }

    private function ready(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Throwable) {
            return false;
        }
    }
}

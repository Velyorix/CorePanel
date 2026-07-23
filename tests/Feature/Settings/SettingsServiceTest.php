<?php

namespace Tests\Feature\Settings;

use Core\Settings\Models\Setting;
use Core\Settings\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SettingsService $settings;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.settings.cache.enabled' => true,
            'corepanel.settings.cache.store' => 'array',
            'corepanel.settings.cache.prefix' => 'test.settings',
            'corepanel.settings.cache.ttl_seconds' => 3600,
        ]);

        $this->settings = app(SettingsService::class);
        $this->settings->flushCache();
    }

    public function test_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(SettingsService::class),
            app(SettingsService::class),
        );
    }

    public function test_set_and_get_string_persists_to_settings_table(): void
    {
        $this->settings->set('app.timezone_label', 'Europe/Paris', 'string', true);

        $this->assertDatabaseHas('settings', [
            'key' => 'app.timezone_label',
            'value' => 'Europe/Paris',
            'type' => 'string',
            'autoload' => 1,
        ]);
        $this->assertSame('Europe/Paris', $this->settings->getString('app.timezone_label'));
        $this->assertTrue($this->settings->has('app.timezone_label'));
    }

    public function test_set_encrypted_stores_ciphertext_and_decrypts(): void
    {
        $this->settings->setEncrypted('mail.smtp_password', 's3cret-pass');

        $row = Setting::query()->where('key', 'mail.smtp_password')->firstOrFail();

        $this->assertSame('encrypted', $row->type);
        $this->assertNotSame('s3cret-pass', $row->value);
        $this->assertSame('s3cret-pass', $this->settings->getEncrypted('mail.smtp_password'));
        $this->assertSame('s3cret-pass', $this->settings->get('mail.smtp_password'));
    }

    public function test_get_encrypted_returns_null_on_invalid_ciphertext(): void
    {
        Setting::query()->create([
            'key' => 'broken.secret',
            'value' => 'not-valid-ciphertext',
            'type' => 'encrypted',
            'autoload' => false,
            'updated_at' => now(),
        ]);

        $this->assertNull($this->settings->getEncrypted('broken.secret'));
        $this->assertNull($this->settings->get('broken.secret'));
    }

    public function test_boolean_integer_json_round_trip(): void
    {
        $this->settings->set('feature.enabled', true, 'boolean');
        $this->settings->set('feature.disabled', false, 'boolean');
        $this->settings->set('feature.limit', 42, 'integer');
        $this->settings->set('feature.zero', 0, 'integer');
        $this->settings->set('feature.meta', ['a' => 1, 'b' => 'x'], 'json');

        $this->assertTrue($this->settings->getBool('feature.enabled'));
        $this->assertFalse($this->settings->getBool('feature.disabled'));
        $this->assertSame(42, $this->settings->getInt('feature.limit'));
        $this->assertSame(0, $this->settings->getInt('feature.zero'));
        $this->assertSame(['a' => 1, 'b' => 'x'], $this->settings->getJson('feature.meta'));
    }

    public function test_cache_hit_returns_stale_until_invalidated(): void
    {
        $this->settings->set('cached.value', 'first');
        $this->assertSame('first', $this->settings->get('cached.value'));

        Setting::query()->where('key', 'cached.value')->update([
            'value' => 'second',
            'updated_at' => now(),
        ]);

        $this->assertSame('first', $this->settings->get('cached.value'));

        $this->settings->forget('cached.value');
        $this->settings->set('cached.value', 'third');

        $this->assertSame('third', $this->settings->get('cached.value'));
    }

    public function test_autoload_bundle_loads_only_autoload_true(): void
    {
        $this->settings->set('autoload.one', 'A', 'string', true);
        $this->settings->set('autoload.two', 'B', 'string', true);
        $this->settings->set('manual.only', 'C', 'string', false);

        $bundle = $this->settings->allAutoloaded();

        $this->assertSame('A', $bundle['autoload.one'] ?? null);
        $this->assertSame('B', $bundle['autoload.two'] ?? null);
        $this->assertArrayNotHasKey('manual.only', $bundle);
    }

    public function test_forget_removes_row_and_cache(): void
    {
        $this->settings->set('temp.key', 'gone-soon');
        $this->assertTrue($this->settings->has('temp.key'));

        $this->settings->forget('temp.key');

        $this->assertFalse($this->settings->has('temp.key'));
        $this->assertDatabaseMissing('settings', ['key' => 'temp.key']);
        $this->assertSame('fallback', $this->settings->get('temp.key', 'fallback'));
    }

    public function test_missing_key_returns_default(): void
    {
        $this->assertNull($this->settings->get('missing.key'));
        $this->assertSame('default', $this->settings->get('missing.key', 'default'));
        $this->assertFalse($this->settings->getBool('missing.bool', false));
        $this->assertSame(7, $this->settings->getInt('missing.int', 7));
    }

    public function test_unsupported_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings->set('bad.type', 'x', 'xml');
    }

    public function test_encrypted_round_trip_matches_crypt_helper(): void
    {
        $this->settings->setEncrypted('crypt.check', 'payload');

        $row = Setting::query()->where('key', 'crypt.check')->firstOrFail();

        $this->assertSame('payload', Crypt::decryptString((string) $row->value));
    }

    public function test_cache_can_be_disabled(): void
    {
        config(['corepanel.settings.cache.enabled' => false]);

        $this->settings->set('nocache.value', 'one');
        Setting::query()->where('key', 'nocache.value')->update(['value' => 'two']);

        $this->assertSame('two', $this->settings->get('nocache.value'));
    }
}

<?php

namespace Tests\Feature\Services;

use Core\Services\Models\Service;
use Core\Services\Models\ServiceConfig;
use Core\Services\Services\ServiceConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServiceConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    private ServiceConfigService $configs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configs = app(ServiceConfigService::class);
    }

    public function test_service_config_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ServiceConfigService::class),
            app(ServiceConfigService::class),
        );
    }

    public function test_get_returns_empty_array_when_missing(): void
    {
        $service = Service::factory()->create();

        $this->assertSame([], $this->configs->get($service));
        $this->assertNull($service->config);
    }

    public function test_set_encrypts_at_rest_and_decrypts_on_read(): void
    {
        $service = Service::factory()->create();

        $payload = [
            'ip_address' => '203.0.113.10',
            'hostname' => 'node-01.example.test',
            'credentials' => [
                'username' => 'root',
                'password' => 'super-secret-password',
            ],
            'metadata' => [
                'external_panel_id' => 'srv-42',
            ],
        ];

        $this->configs->set($service, $payload);

        $raw = DB::table('service_config')->where('service_id', $service->id)->value('data');

        $this->assertIsString($raw);
        $this->assertNotSame(json_encode($payload), $raw);
        $this->assertStringNotContainsString('super-secret-password', $raw);
        $this->assertStringNotContainsString('203.0.113.10', $raw);

        $this->assertSame($payload, $this->configs->get($service->fresh() ?? $service));
    }

    public function test_set_and_merge_sync_denormalized_ip_and_hostname(): void
    {
        $service = Service::factory()->create([
            'ip_address' => null,
            'hostname' => null,
        ]);

        $this->configs->set($service, [
            'ip_address' => '198.51.100.20',
            'hostname' => 'game-01.example.test',
            'credentials' => ['api_key' => 'key-1'],
        ]);

        $service = $service->fresh() ?? $service;
        $this->assertSame('198.51.100.20', $service->ip_address);
        $this->assertSame('game-01.example.test', $service->hostname);

        $this->configs->merge($service, [
            'hostname' => 'game-02.example.test',
            'metadata' => ['region' => 'eu'],
        ]);

        $service = $service->fresh() ?? $service;
        $this->assertSame('game-02.example.test', $service->hostname);
        $this->assertSame('198.51.100.20', $service->ip_address);
        $this->assertSame([
            'ip_address' => '198.51.100.20',
            'hostname' => 'game-02.example.test',
            'credentials' => ['api_key' => 'key-1'],
            'metadata' => ['region' => 'eu'],
        ], $this->configs->get($service));
    }

    public function test_merge_preserves_existing_keys(): void
    {
        $service = Service::factory()->create();

        $this->configs->set($service, [
            'credentials' => ['username' => 'admin', 'password' => 'keep-me'],
            'metadata' => ['a' => 1],
        ]);

        $this->configs->merge($service, [
            'metadata' => ['b' => 2],
        ]);

        $this->assertSame([
            'credentials' => ['username' => 'admin', 'password' => 'keep-me'],
            'metadata' => ['b' => 2],
        ], $this->configs->get($service));
    }

    public function test_forget_removes_config_row(): void
    {
        $service = Service::factory()->create();
        $this->configs->set($service, ['hostname' => 'tmp.example.test']);

        $this->configs->forget($service);

        $this->assertSame(0, ServiceConfig::query()->where('service_id', $service->id)->count());
        $this->assertSame([], $this->configs->get($service->fresh() ?? $service));
    }

    public function test_service_config_relation_and_cascade_delete(): void
    {
        $service = Service::factory()->create();
        $this->configs->set($service, ['ip_address' => '192.0.2.1']);

        $this->assertInstanceOf(ServiceConfig::class, $service->fresh()->config);
        $this->assertSame(1, ServiceConfig::query()->count());

        $service->forceDelete();

        $this->assertSame(0, ServiceConfig::query()->count());
    }

    public function test_config_data_on_service_stays_separate_from_encrypted_blob(): void
    {
        $service = Service::factory()->create([
            'config_data' => [
                'options' => ['ram' => '8'],
                'addons' => ['backup'],
            ],
        ]);

        $this->configs->set($service, [
            'credentials' => ['password' => 'secret'],
        ]);

        $this->assertSame([
            'options' => ['ram' => '8'],
            'addons' => ['backup'],
        ], $service->fresh()->config_data);
        $this->assertSame([
            'credentials' => ['password' => 'secret'],
        ], $this->configs->get($service));
    }
}

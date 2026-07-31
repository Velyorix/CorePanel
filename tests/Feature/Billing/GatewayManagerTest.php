<?php

namespace Tests\Feature\Billing;

use Core\Billing\Exceptions\DisabledPaymentGatewayException;
use Core\Billing\Exceptions\UnknownPaymentGatewayException;
use Core\Billing\Gateways\ManualTransferGateway;
use Core\Billing\Models\PaymentGateway;
use Core\Billing\Services\GatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\TestCase;

class GatewayManagerTest extends TestCase
{
    use RefreshDatabase;

    private GatewayManager $gateways;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateways = app(GatewayManager::class);
        $this->gateways->flush();
    }

    public function test_gateway_manager_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(GatewayManager::class),
            app(GatewayManager::class),
        );
    }

    public function test_register_and_resolve_gateways(): void
    {
        $gateway = new FakePaymentGateway;

        $this->gateways->register($gateway);
        $this->gateways->enable('fake');

        $this->assertTrue($this->gateways->has('fake'));
        $this->assertSame($gateway, $this->gateways->resolve('fake'));
        $this->assertSame(['fake'], $this->gateways->keys());
        $this->assertCount(1, $this->gateways->all());
        $this->assertCount(1, $this->gateways->enabled());
    }

    public function test_resolve_unknown_gateway_throws(): void
    {
        $this->expectException(UnknownPaymentGatewayException::class);
        $this->expectExceptionMessage('Unknown payment gateway [missing].');

        $this->gateways->resolve('missing');
    }

    public function test_resolve_disabled_gateway_throws_when_only_enabled(): void
    {
        $this->gateways->register(new FakePaymentGateway);
        $this->gateways->sync();

        $this->assertFalse($this->gateways->isEnabled('fake'));

        $this->expectException(DisabledPaymentGatewayException::class);
        $this->expectExceptionMessage('Payment gateway [fake] is disabled.');

        $this->gateways->resolve('fake');
    }

    public function test_resolve_allows_disabled_when_only_enabled_false(): void
    {
        $gateway = new FakePaymentGateway;
        $this->gateways->register($gateway);
        $this->gateways->sync();

        $this->assertSame($gateway, $this->gateways->resolve('fake', onlyEnabled: false));
    }

    public function test_register_overwrites_same_key(): void
    {
        $first = new FakePaymentGateway(label: 'First');
        $second = new FakePaymentGateway(label: 'Second');

        $this->gateways->register($first);
        $this->gateways->register($second);
        $this->gateways->enable('fake');

        $this->assertSame('Second', $this->gateways->resolve('fake')->label());
        $this->assertCount(1, $this->gateways->all());
    }

    public function test_enable_disable_and_configure_persist_encrypted_config(): void
    {
        $this->gateways->register(new FakePaymentGateway);

        $enabled = $this->gateways->enable('fake');
        $this->assertTrue($enabled->enabled);
        $this->assertDatabaseHas('payment_gateways', [
            'key' => 'fake',
            'enabled' => true,
        ]);

        $configured = $this->gateways->configure('fake', [
            'api_key' => 'sk_test_secret',
            'mode' => 'test',
        ]);

        $this->assertSame('sk_test_secret', $configured->config['api_key'] ?? null);
        $this->assertSame('test', $configured->config['mode'] ?? null);
        $this->assertArrayNotHasKey('config', $configured->toArray());

        $raw = PaymentGateway::query()->where('key', 'fake')->firstOrFail()->getAttributes()['config'] ?? null;
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('sk_test_secret', $raw);

        $merged = $this->gateways->configure('fake', ['mode' => 'live']);
        $this->assertSame('sk_test_secret', $merged->config['api_key'] ?? null);
        $this->assertSame('live', $merged->config['mode'] ?? null);

        $disabled = $this->gateways->disable('fake');
        $this->assertFalse($disabled->enabled);
        $this->assertSame([], $this->gateways->enabled());
    }

    public function test_sync_creates_rows_for_registered_gateways_without_enabling(): void
    {
        $this->gateways->register(new FakePaymentGateway(key: 'alpha'));
        $this->gateways->register(new FakePaymentGateway(key: 'beta'));
        $this->gateways->sync();

        $this->assertDatabaseHas('payment_gateways', [
            'key' => 'alpha',
            'enabled' => false,
        ]);
        $this->assertDatabaseHas('payment_gateways', [
            'key' => 'beta',
            'enabled' => false,
        ]);

        $this->gateways->enable('beta');
        $this->gateways->configure('beta', ['token' => 'keep-me']);
        $this->gateways->sync();

        $this->assertDatabaseHas('payment_gateways', [
            'key' => 'beta',
            'enabled' => true,
        ]);
        $this->assertSame('keep-me', $this->gateways->record('beta')?->config['token'] ?? null);
    }

    public function test_manual_transfer_is_registered_and_enabled_on_first_boot(): void
    {
        $manager = app(GatewayManager::class);
        $manager->register(app(ManualTransferGateway::class));
        PaymentGateway::query()->where('key', ManualTransferGateway::KEY)->delete();
        $manager->sync();

        $this->assertTrue($manager->has(ManualTransferGateway::KEY));
        $this->assertTrue($manager->isEnabled(ManualTransferGateway::KEY));
        $this->assertDatabaseHas('payment_gateways', [
            'key' => ManualTransferGateway::KEY,
            'enabled' => true,
        ]);
    }

    public function test_enabled_list_respects_sort_order(): void
    {
        $this->gateways->register(new FakePaymentGateway(key: 'second', label: 'Second'));
        $this->gateways->register(new FakePaymentGateway(key: 'first', label: 'First'));
        $this->gateways->sync();

        PaymentGateway::query()->where('key', 'first')->update(['sort_order' => 1, 'enabled' => true]);
        PaymentGateway::query()->where('key', 'second')->update(['sort_order' => 2, 'enabled' => true]);

        $enabled = $this->gateways->enabled();

        $this->assertSame(['first', 'second'], array_map(
            static fn ($gateway): string => $gateway->key(),
            $enabled,
        ));
    }
}

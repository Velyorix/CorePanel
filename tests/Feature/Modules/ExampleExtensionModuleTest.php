<?php

namespace Tests\Feature\Modules;

use Core\Billing\Events\InvoicePaid;
use Core\Billing\Models\Invoice;
use Core\Modules\Enums\ModuleCapability;
use Core\Modules\Enums\ModuleProfile;
use Core\Modules\Services\ModuleHookRegistry;
use Core\Modules\Services\ModuleManager;
use Core\Orders\Events\OrderPaid;
use Core\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\ExampleExtension\ExampleExtensionModule;
use Modules\ExampleExtension\Notifications\ExampleNotificationChannel;
use Modules\ExampleExtension\Providers\ExampleExtensionServiceProvider;
use Tests\TestCase;

class ExampleExtensionModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.modules.path' => base_path('Modules'),
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
            'corepanel.modules.sandbox.enabled' => true,
            'corepanel.modules.signature.required' => false,
            'corepanel.modules.signature.verify_on_load' => false,
        ]);

        $this->app->forgetInstance(ModuleManager::class);
        $this->app->forgetInstance(ExampleNotificationChannel::class);
        app(ExampleNotificationChannel::class)->flush();
        app(ModuleHookRegistry::class)->flush();
    }

    public function test_example_extension_package_is_discovered_as_extension_module(): void
    {
        $manifest = app(ModuleManager::class)->discover()->firstWhere('key', 'example_extension');

        $this->assertNotNull($manifest);
        $this->assertSame('Example Extension', $manifest->name);
        $this->assertSame(ModuleProfile::Extension, $manifest->profile());
        $this->assertTrue($manifest->hasCapability(ModuleCapability::Extension->value));
        $this->assertTrue($manifest->hasCapability(ModuleCapability::NotificationChannel->value));
        $this->assertSame(['invoice.paid', 'order.paid'], $manifest->hookManifest->events);
        $this->assertContains(ExampleExtensionServiceProvider::class, $manifest->providers);
        $this->assertSame(ExampleExtensionModule::class, $manifest->moduleClass);
    }

    public function test_enable_registers_event_listeners_on_hook_registry(): void
    {
        app(ModuleManager::class)->enable('example_extension');

        $registry = app(ModuleHookRegistry::class);

        $this->assertGreaterThanOrEqual(1, $registry->eventCount('invoice.paid'));
        $this->assertGreaterThanOrEqual(1, $registry->eventCount('order.paid'));
    }

    public function test_invoice_paid_event_delivers_stub_notification(): void
    {
        app(ModuleManager::class)->enable('example_extension');

        $invoice = Invoice::factory()->paid()->create();

        Event::dispatch(new InvoicePaid($invoice));

        $deliveries = app(ExampleNotificationChannel::class)->deliveries();

        $this->assertCount(1, $deliveries);
        $this->assertSame('invoice.paid', $deliveries[0]['event']);
        $this->assertSame($invoice->id, $deliveries[0]['context']['invoice_id']);
        $this->assertSame($invoice->client_id, $deliveries[0]['context']['client_id']);
    }

    public function test_order_paid_event_delivers_stub_notification(): void
    {
        app(ModuleManager::class)->enable('example_extension');

        $order = Order::factory()->paid()->create();

        Event::dispatch(new OrderPaid($order));

        $deliveries = app(ExampleNotificationChannel::class)->deliveries();

        $this->assertCount(1, $deliveries);
        $this->assertSame('order.paid', $deliveries[0]['event']);
        $this->assertSame($order->id, $deliveries[0]['context']['order_id']);
    }

    public function test_disable_removes_event_listeners_and_stops_deliveries(): void
    {
        $manager = app(ModuleManager::class);
        $manager->enable('example_extension');

        $invoice = Invoice::factory()->paid()->create();
        Event::dispatch(new InvoicePaid($invoice));

        $this->assertCount(1, app(ExampleNotificationChannel::class)->deliveries());

        $manager->disable('example_extension');

        $this->assertFalse(app(ModuleHookRegistry::class)->hasEvent('invoice.paid'));

        Event::dispatch(new InvoicePaid(Invoice::factory()->paid()->create()));

        $this->assertCount(0, app(ExampleNotificationChannel::class)->deliveries());
    }
}

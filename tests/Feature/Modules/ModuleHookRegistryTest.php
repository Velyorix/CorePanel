<?php

namespace Tests\Feature\Modules;

use Core\Modules\Enums\ModuleCapability;
use Core\Modules\Services\ModuleHookRegistry;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModuleSandbox;
use Core\Modules\Services\ModuleStateRepository;
use Core\Modules\Services\ModuleViewRegistrar;
use Core\Orders\Events\OrderPaid;
use Core\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\Support\Modules\StubHookedModule;
use Tests\TestCase;

class ModuleHookRegistryTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/modules-hooks-'.uniqid('', true));
        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
            'corepanel.modules.signature.required' => false,
            'corepanel.modules.signature.verify_on_load' => false,
            'corepanel.modules.hooks.laravel_events' => [
                'order.paid' => OrderPaid::class,
            ],
        ]);

        app(ModuleHookRegistry::class)->flush();
        $this->app->forgetInstance(ModuleManager::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_run_hook_invokes_listeners_by_priority(): void
    {
        $registry = app(ModuleHookRegistry::class);
        $calls = [];

        $registry->registerHook('checkout.summary', function () use (&$calls): void {
            $calls[] = 'second';
        }, priority: 20);
        $registry->registerHook('checkout.summary', function () use (&$calls): void {
            $calls[] = 'first';
        }, priority: 5);

        $registry->runHook('checkout.summary');

        $this->assertSame(['first', 'second'], $calls);
    }

    public function test_apply_filter_chains_values_through_listeners(): void
    {
        $registry = app(ModuleHookRegistry::class);

        $registry->registerFilter('invoice.total', fn (int $total): int => $total + 10, priority: 10);
        $registry->registerFilter('invoice.total', fn (int $total): int => $total * 2, priority: 20);

        $result = $registry->applyFilter('invoice.total', 5);

        $this->assertSame(30, $result);
    }

    public function test_dispatch_event_notifies_registered_listeners(): void
    {
        $registry = app(ModuleHookRegistry::class);
        $payload = null;

        $registry->listenEvent('invoice.paid', function (string $message) use (&$payload): void {
            $payload = $message;
        });

        $registry->dispatchEvent('invoice.paid', 'paid');

        $this->assertSame('paid', $payload);
    }

    public function test_forget_module_removes_only_that_modules_listeners(): void
    {
        $registry = app(ModuleHookRegistry::class);
        $calls = [];

        $registry->registerHook('demo', function () use (&$calls): void {
            $calls[] = 'core';
        }, moduleKey: null);
        $registry->registerHook('demo', function () use (&$calls): void {
            $calls[] = 'module';
        }, moduleKey: 'alpha');

        $registry->forgetModule('alpha');
        $registry->runHook('demo');

        $this->assertSame(['core'], $calls);
    }

    public function test_module_listener_runs_inside_sandbox_context(): void
    {
        $registry = app(ModuleHookRegistry::class);
        $sandbox = app(ModuleSandbox::class);
        $activeKey = null;

        $registry->registerHook('sandbox.probe', function () use ($sandbox, &$activeKey): void {
            $activeKey = $sandbox->currentModuleKey();
        }, moduleKey: 'probe');

        $registry->runHook('sandbox.probe');

        $this->assertSame('probe', $activeKey);
    }

    public function test_helper_functions_delegate_to_registry(): void
    {
        $registry = app(ModuleHookRegistry::class);
        $calls = [];

        $registry->registerHook('helpers.demo', function () use (&$calls): void {
            $calls[] = 'hook';
        });
        $registry->registerFilter('helpers.filter', fn (int $value): int => $value + 2);

        module_hook('helpers.demo');

        $this->assertSame(['hook'], $calls);
        $this->assertSame(42, module_apply_filter('helpers.filter', 40));
    }

    public function test_enabled_module_registers_hooks_via_host_and_interface(): void
    {
        $this->writeModule('hooked', [
            'name' => 'hooked',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::Other->value],
            'module' => StubHookedModule::class,
        ]);

        $manager = app(ModuleManager::class);
        $manager->enable('hooked');

        /** @var StubHookedModule $instance */
        $instance = $manager->instance('hooked');

        app(ModuleHookRegistry::class)->runHook('demo.boot');
        app(ModuleHookRegistry::class)->runHook('demo.extension');

        $this->assertSame(['boot', 'interface'], $instance->hookCalls);
    }

    public function test_disabling_module_removes_its_hook_listeners(): void
    {
        $this->writeModule('hooked', [
            'name' => 'hooked',
            'version' => '1.0.0',
            'capabilities' => [ModuleCapability::Other->value],
            'module' => StubHookedModule::class,
        ]);

        $manager = app(ModuleManager::class);
        $registry = app(ModuleHookRegistry::class);

        $manager->enable('hooked');
        $manager->disable('hooked');

        $this->assertFalse($registry->hasHook('demo.boot'));
        $this->assertFalse($registry->hasHook('demo.extension'));
    }

    public function test_laravel_event_bridge_forwards_to_module_event_listeners(): void
    {
        Event::forget(OrderPaid::class);
        app(\Core\Modules\Services\ModuleEventBridge::class)->register();

        $registry = app(ModuleHookRegistry::class);
        $received = null;

        $registry->listenEvent('order.paid', function (OrderPaid $event) use (&$received): void {
            $received = $event->order->id;
        });

        $order = Order::factory()->paid()->create();

        Event::dispatch(new OrderPaid($order));

        $this->assertSame($order->id, $received);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeModule(string $directory, array $manifest): void
    {
        $path = $this->modulesPath.'/'.$directory;
        File::ensureDirectoryExists($path);
        File::put(
            $path.'/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }
}

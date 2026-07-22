<?php

namespace Tests\Feature\Extensions;

use Composer\Autoload\ClassLoader;
use Core\Billing\Events\InvoicePaid;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\GatewayManager;
use Core\Modules\Enums\ModuleScaffoldProfile;
use Core\Modules\Services\InstalledModuleRepository;
use Core\Modules\Services\ModuleFactory;
use Core\Modules\Services\ModuleGenerator;
use Core\Modules\Services\ModuleHookRegistry;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModulePackageHasher;
use Core\Modules\Services\ModuleRequirementChecker;
use Core\Modules\Services\ModuleResourceLoader;
use Core\Modules\Services\ModuleSandbox;
use Core\Modules\Services\ModuleScaffolder;
use Core\Modules\Services\ModuleServiceProviderRegistrar;
use Core\Modules\Services\ModuleSignatureVerifier;
use Core\Themes\Services\ThemeGenerator;
use Core\Themes\Services\ThemeManager;
use Core\Themes\Services\ThemeScaffolder;
use Core\Themes\Services\ThemeStateRepository;
use Core\Themes\Services\ThemeViewRegistrar;
use Core\Themes\Services\ThemeViteBuilder;
use Core\Themes\Services\ThemeViteEntryResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Cross-cutting Feature smoke for themes, module hooks, and Artisan scaffolding.
 */
class ThemeModuleArtisanFeatureTest extends TestCase
{
    use RefreshDatabase;

    private string $themesPath;

    private string $modulesPath;

    /** @var list<callable(string): void> */
    private array $autoloadRemovers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->themesPath = storage_path('framework/testing/themes-extensions-'.uniqid('', true));
        $this->modulesPath = storage_path('framework/testing/modules-extensions-'.uniqid('', true));

        File::ensureDirectoryExists($this->themesPath);
        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.themes.path' => $this->themesPath,
            'corepanel.themes.default' => 'default',
            'corepanel.themes.auto_load_active' => false,
            'corepanel.themes.override_module_views' => true,
            'corepanel.themes.vite.binary' => 'npx',
            'corepanel.themes.vite.package' => 'vite',
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
            'corepanel.modules.sandbox.enabled' => true,
            'corepanel.modules.signature.required' => false,
            'corepanel.modules.signature.verify_on_load' => false,
            'session.driver' => 'array',
        ]);

        $this->withoutVite();
        $this->rebindThemeServices();
        $this->rebindModuleStack();
        app(GatewayManager::class)->flush();
        app(ModuleHookRegistry::class)->flush();
    }

    protected function tearDown(): void
    {
        foreach ($this->autoloadRemovers as $remover) {
            $remover();
        }

        File::deleteDirectory($this->themesPath);
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_activating_theme_changes_view_render_without_redeploy(): void
    {
        $this->writeTheme('default', ['name' => 'default', 'label' => 'Default'], views: [
            'overrides/smoke.blade.php' => '<p>Default theme</p>',
        ]);
        $this->writeTheme('branded', ['name' => 'branded', 'label' => 'Branded'], views: [
            'overrides/smoke.blade.php' => '<p>Branded theme</p>',
        ]);

        File::ensureDirectoryExists(resource_path('views/overrides'));
        File::put(resource_path('views/overrides/smoke.blade.php'), '<p>Core fallback</p>');

        $themes = app(ThemeManager::class);
        $themes->activate('default');

        $this->assertSame('<p>Default theme</p>', trim(View::make('overrides.smoke')->render()));

        $themes->activate('branded');

        $this->assertSame('<p>Branded theme</p>', trim(View::make('overrides.smoke')->render()));
        $this->assertSame('branded', $themes->activeKey());
    }

    public function test_theme_preview_applies_without_changing_global_active_theme(): void
    {
        $this->writeTheme('default', ['name' => 'default', 'label' => 'Default'], views: [
            'overrides/preview.blade.php' => '<p>Default preview</p>',
        ]);
        $this->writeTheme('aurora', ['name' => 'aurora', 'label' => 'Aurora'], views: [
            'overrides/preview.blade.php' => '<p>Aurora preview</p>',
        ]);

        File::ensureDirectoryExists(resource_path('views/overrides'));
        File::put(resource_path('views/overrides/preview.blade.php'), '<p>Core preview</p>');

        $themes = app(ThemeManager::class);
        $themes->activate('default');
        $session = session()->driver();
        $themes->preview('aurora', $session);

        $this->assertSame('default', $themes->activeKey());
        $this->assertSame('aurora', $themes->previewKey($session));
        $this->assertSame('<p>Aurora preview</p>', trim(View::make('overrides.preview')->render()));

        $themes->clearPreview($session);

        $this->assertNull($themes->previewKey($session));
        $this->assertSame('<p>Default preview</p>', trim(View::make('overrides.preview')->render()));
    }

    public function test_theme_make_demo_and_theme_build_demo_succeed(): void
    {
        $this->assertSame(0, Artisan::call('theme:make', [
            'name' => 'Demo',
            '--author' => 'Velyorix',
        ]));

        $this->assertTrue(app(ThemeManager::class)->has('demo'));
        $this->assertFileExists($this->themesPath.'/Demo/theme.json');

        Process::fake([
            '*' => Process::result(exitCode: 0),
        ]);

        $this->assertSame(0, Artisan::call('theme:build', ['theme' => 'demo']));

        Process::assertRan(function (PendingProcess $process): bool {
            $inputs = $process->environment['COREPANEL_VITE_INPUTS'] ?? null;

            return $process->command === ['npx', 'vite', 'build']
                && is_string($inputs)
                && str_contains($inputs, 'Demo/resources/css/theme.css');
        });
    }

    public function test_scaffolded_extension_module_is_installable_and_listens_to_invoice_paid(): void
    {
        $this->assertSame(0, Artisan::call('module:make', [
            'name' => 'Smoke Notify',
            '--profile' => ModuleScaffoldProfile::Extension->value,
            '--key' => 'smoke_notify',
        ]));

        $root = $this->modulesPath.'/SmokeNotify';
        $this->registerPsr4('Modules\\SmokeNotify\\', $root.DIRECTORY_SEPARATOR);

        $modules = app(ModuleManager::class);
        $modules->discover(refresh: true);

        $this->assertTrue($modules->has('smoke_notify'));

        $modules->install('smoke_notify', enable: true);

        $this->assertTrue($modules->isEnabled('smoke_notify'));
        $this->assertTrue($modules->isLoaded('smoke_notify'));
        $this->assertGreaterThanOrEqual(1, app(ModuleHookRegistry::class)->eventCount('invoice.paid'));

        $invoice = Invoice::factory()->paid()->create();
        Event::dispatch(new InvoicePaid($invoice));

        $channel = app(\Modules\SmokeNotify\Notifications\SmokeNotifyNotificationChannel::class);
        $deliveries = $channel->deliveries();

        $this->assertCount(1, $deliveries);
        $this->assertSame('invoice.paid', $deliveries[0]['event']);
        $this->assertSame($invoice->id, $deliveries[0]['context']['invoice_id']);
    }

    public function test_module_make_gateway_stub_registers_in_gateway_manager(): void
    {
        $this->assertSame(0, Artisan::call('module:make', [
            'name' => 'Smoke Pay',
            '--profile' => ModuleScaffoldProfile::Integration->value,
            '--key' => 'smoke_pay',
        ]));

        $root = $this->modulesPath.'/SmokePay';
        $this->registerPsr4('Modules\\SmokePay\\', $root.DIRECTORY_SEPARATOR);

        $this->assertSame(0, Artisan::call('module:make:gateway', [
            'name' => 'Acme',
            '--module' => 'smoke_pay',
        ]));

        $gatewayClass = 'Modules\\SmokePay\\Gateways\\AcmeGateway';
        $this->assertFileExists($root.'/Gateways/AcmeGateway.php');

        $manifest = json_decode(File::get($root.'/module.json'), true);
        $this->assertContains($gatewayClass, $manifest['gateways']);

        app(ModuleManager::class)->discover(refresh: true);
        app(ModuleManager::class)->enable('smoke_pay');

        $gateways = app(GatewayManager::class);

        $this->assertTrue($gateways->has('smoke_pay_acme_gateway'));
        $this->assertSame(
            $gatewayClass,
            $gateways->resolve('smoke_pay_acme_gateway', onlyEnabled: false)::class,
        );
    }

    private function registerPsr4(string $namespace, string $path): void
    {
        /** @var ClassLoader $loader */
        $loader = require base_path('vendor/autoload.php');
        $loader->addPsr4($namespace, $path);

        $this->autoloadRemovers[] = static function () use ($loader, $namespace): void {
            $loader->setPsr4($namespace, []);
        };
    }

    private function rebindThemeServices(): void
    {
        $this->app->forgetInstance(ThemeManager::class);
        $this->app->forgetInstance(ThemeStateRepository::class);
        $this->app->forgetInstance(ThemeViewRegistrar::class);
        $this->app->forgetInstance(ThemeViteEntryResolver::class);
        $this->app->forgetInstance(ThemeViteBuilder::class);
        $this->app->forgetInstance(ThemeScaffolder::class);
        $this->app->forgetInstance(ThemeGenerator::class);
    }

    private function rebindModuleStack(): void
    {
        $this->app->forgetInstance(ModulePackageHasher::class);
        $this->app->forgetInstance(ModuleSignatureVerifier::class);
        $this->app->forgetInstance(InstalledModuleRepository::class);
        $this->app->forgetInstance(ModuleFactory::class);
        $this->app->forgetInstance(ModuleRequirementChecker::class);
        $this->app->forgetInstance(ModuleServiceProviderRegistrar::class);
        $this->app->forgetInstance(ModuleResourceLoader::class);
        $this->app->forgetInstance(ModuleScaffolder::class);
        $this->app->forgetInstance(ModuleGenerator::class);
        $this->app->forgetInstance(ModuleManager::class);

        $this->app->singleton(ModuleManager::class, fn (): ModuleManager => new ModuleManager(
            app(InstalledModuleRepository::class),
            app(ModuleFactory::class),
            app(ModuleRequirementChecker::class),
            app(ModuleSandbox::class),
            app(ModuleServiceProviderRegistrar::class),
            app(ModuleResourceLoader::class),
            $this->modulesPath,
            app(\Core\Billing\Services\PaymentGatewayInjector::class),
            app(ModuleHookRegistry::class),
        ));
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, string>  $views
     */
    private function writeTheme(string $directory, array $manifest, array $views = []): void
    {
        $path = $this->themesPath.'/'.$directory;
        File::ensureDirectoryExists($path);
        File::put(
            $path.'/theme.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        foreach ($views as $relative => $contents) {
            $absolute = $path.'/resources/views/'.$relative;
            File::ensureDirectoryExists(dirname($absolute));
            File::put($absolute, $contents);
        }
    }
}

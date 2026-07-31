<?php

namespace Tests\Feature\Modules;

use Core\Modules\Exceptions\ModuleSignatureException;
use Core\Modules\Models\InstalledModule;
use Core\Modules\Services\InstalledModuleRepository;
use Core\Modules\Services\ModuleFactory;
use Core\Modules\Services\ModuleManager;
use Core\Modules\Services\ModulePackageHasher;
use Core\Modules\Services\ModuleRequirementChecker;
use Core\Modules\Services\ModuleResourceLoader;
use Core\Modules\Services\ModuleSandbox;
use Core\Modules\Services\ModuleServiceProviderRegistrar;
use Core\Modules\Services\ModuleSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleInstallationTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/modules-install-'.uniqid('', true));
        File::ensureDirectoryExists($this->modulesPath);

        config([
            'corepanel.modules.path' => $this->modulesPath,
            'corepanel.modules.enabled' => [],
            'corepanel.modules.auto_load_enabled' => false,
            'corepanel.modules.sandbox.enabled' => true,
            'corepanel.modules.signature.required' => false,
            'corepanel.modules.signature.verify_on_load' => true,
            'corepanel.modules.signature.secret' => 'test-module-secret',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        ]);

        $this->rebindModuleManager();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_install_persists_version_checksum_and_signature(): void
    {
        $this->writeModule('tracker', [
            'name' => 'tracker',
            'version' => '1.2.0',
            'capabilities' => [],
        ]);

        $record = app(ModuleManager::class)->install('tracker');

        $this->assertDatabaseHas('installed_modules', [
            'name' => 'tracker',
            'version' => '1.2.0',
            'enabled' => false,
        ]);

        $this->assertNotNull($record->checksum);
        $this->assertSame(64, strlen((string) $record->checksum));
        $this->assertNotNull($record->signature);
        $this->assertNotNull($record->installed_at);
    }

    public function test_enable_marks_module_enabled_in_registry(): void
    {
        $this->writeModule('tracker', [
            'name' => 'tracker',
            'version' => '1.0.0',
            'capabilities' => [],
        ]);

        app(ModuleManager::class)->enable('tracker');

        $this->assertDatabaseHas('installed_modules', [
            'name' => 'tracker',
            'enabled' => true,
            'version' => '1.0.0',
        ]);

        $this->assertTrue(app(ModuleManager::class)->isEnabled('tracker'));
        $this->assertNotNull(InstalledModule::query()->where('name', 'tracker')->first()?->enabled_at);
    }

    public function test_rejects_tampered_checksum_when_declared(): void
    {
        $this->writeModule('secure', [
            'name' => 'secure',
            'version' => '1.0.0',
            'capabilities' => [],
            'checksum' => str_repeat('0', 64),
        ]);

        $this->expectException(ModuleSignatureException::class);
        $this->expectExceptionMessage('integrity verification');

        app(ModuleManager::class)->install('secure');
    }

    public function test_accepts_valid_declared_checksum(): void
    {
        $this->writeModule('secure', [
            'name' => 'secure',
            'version' => '1.0.0',
            'capabilities' => [],
        ]);

        $checksum = app(ModulePackageHasher::class)->hash(
            app(ModuleManager::class)->get('secure')
                ?? throw new \RuntimeException('missing module'),
        );

        $this->writeModule('secure', [
            'name' => 'secure',
            'version' => '1.0.0',
            'capabilities' => [],
            'checksum' => $checksum,
        ]);

        // Refresh discovery cache after rewriting manifest.
        $this->rebindModuleManager();

        $record = app(ModuleManager::class)->install('secure');

        $this->assertTrue(hash_equals($checksum, (string) $record->checksum));
    }

    public function test_requires_checksum_when_signature_enforcement_is_enabled(): void
    {
        config(['corepanel.modules.signature.required' => true]);

        $this->writeModule('strict', [
            'name' => 'strict',
            'version' => '1.0.0',
            'capabilities' => [],
        ]);

        $this->expectException(ModuleSignatureException::class);
        $this->expectExceptionMessage('missing a checksum/signature');

        app(ModuleManager::class)->install('strict');
    }

    public function test_disable_and_uninstall_update_registry(): void
    {
        $this->writeModule('tracker', [
            'name' => 'tracker',
            'version' => '1.0.0',
            'capabilities' => [],
        ]);

        $manager = app(ModuleManager::class);
        $manager->enable('tracker');
        $manager->disable('tracker');

        $this->assertDatabaseHas('installed_modules', [
            'name' => 'tracker',
            'enabled' => false,
        ]);

        $this->assertTrue($manager->uninstall('tracker'));
        $this->assertDatabaseMissing('installed_modules', ['name' => 'tracker']);
    }

    private function rebindModuleManager(): void
    {
        $this->app->forgetInstance(ModulePackageHasher::class);
        $this->app->forgetInstance(ModuleSignatureVerifier::class);
        $this->app->forgetInstance(InstalledModuleRepository::class);
        $this->app->forgetInstance(ModuleFactory::class);
        $this->app->forgetInstance(ModuleRequirementChecker::class);
        $this->app->forgetInstance(ModuleServiceProviderRegistrar::class);
        $this->app->forgetInstance(ModuleResourceLoader::class);
        $this->app->forgetInstance(ModuleManager::class);

        $this->app->singleton(ModuleManager::class, fn (): ModuleManager => new ModuleManager(
            app(InstalledModuleRepository::class),
            app(ModuleFactory::class),
            app(ModuleRequirementChecker::class),
            app(ModuleSandbox::class),
            app(ModuleServiceProviderRegistrar::class),
            app(ModuleResourceLoader::class),
            $this->modulesPath,
        ));
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

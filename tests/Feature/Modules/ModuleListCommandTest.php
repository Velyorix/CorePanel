<?php

namespace Tests\Feature\Modules;

use Core\Modules\Services\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ModuleListCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_list_includes_discovered_modules(): void
    {
        config([
            'corepanel.modules.path' => base_path('Modules'),
            'corepanel.modules.auto_load_enabled' => false,
        ]);

        $this->app->forgetInstance(ModuleManager::class);

        $exitCode = Artisan::call('module:list');

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();

        $this->assertStringContainsString('example_extension', $output);
        $this->assertStringContainsString('Profile', $output);
        $this->assertStringContainsString('Installed', $output);
    }

    public function test_module_list_filters_by_profile(): void
    {
        config([
            'corepanel.modules.path' => base_path('Modules'),
            'corepanel.modules.auto_load_enabled' => false,
        ]);

        $this->app->forgetInstance(ModuleManager::class);

        Artisan::call('module:list', ['--profile' => 'extension']);

        $output = Artisan::output();

        $this->assertStringContainsString('example_extension', $output);
        $this->assertStringNotContainsString('| example ', $output);
    }
}

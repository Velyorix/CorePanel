<?php

namespace Tests\Support\Modules;

use Core\Modules\Support\AbstractModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StubSandboxModule extends AbstractModule
{
    public bool $touchedOwnTable = false;

    public ?string $coreVersion = null;

    public int $permissionsRegistered = 0;

    public function boot(): void
    {
        $this->coreVersion = $this->host()->coreVersion();
    }

    public function enable(): void
    {
        if (! Schema::hasTable('module_sandbox_probe_settings')) {
            Schema::create('module_sandbox_probe_settings', function ($table): void {
                $table->id();
                $table->string('key');
                $table->timestamps();
            });
        }

        DB::table('module_sandbox_probe_settings')->insert([
            'key' => 'enabled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->touchedOwnTable = true;
        $this->permissionsRegistered = $this->host()->registerPermissions();
    }

    public function readForbiddenConfig(): mixed
    {
        return $this->host()->config('database.default');
    }
}

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

    public ?string $lastLogMessage = null;

    public mixed $allowedConfigValue = null;

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

    public function readAllowedConfig(): mixed
    {
        return $this->allowedConfigValue = $this->host()->config('corepanel.version');
    }

    public function writeLog(string $message = 'sandbox probe log'): void
    {
        $this->host()->log('info', $message, ['probe' => true]);
        $this->lastLogMessage = $message;
    }
}

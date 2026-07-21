<?php

namespace Tests\Support\Modules;

use Core\Modules\Support\AbstractModule;
use Illuminate\Support\Facades\DB;

class StubCoreDbAccessModule extends AbstractModule
{
    public function boot(): void
    {
        DB::table('users')->count();
    }
}

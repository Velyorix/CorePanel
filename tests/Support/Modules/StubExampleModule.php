<?php

namespace Tests\Support\Modules;

use Core\Modules\Support\AbstractModule;

class StubExampleModule extends AbstractModule
{
    public bool $registered = false;

    public bool $booted = false;

    public bool $enabled = false;

    public bool $disabled = false;

    public function register(): void
    {
        $this->registered = true;
    }

    public function boot(): void
    {
        $this->booted = true;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->disabled = true;
        $this->enabled = false;
    }
}

<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

class ComponentShowcaseTest extends TestCase
{
    public function test_showcase_is_available_when_explicitly_enabled(): void
    {
        config(['corepanel.ui.showcase.enabled' => true]);

        $this->get(route('dev.components'))
            ->assertOk()
            ->assertSee('Component showcase', false)
            ->assertSee('x-data="themeToggle"', false)
            ->assertSee('Primary', false)
            ->assertSee('Open modal', false)
            ->assertSee('Bulk delete', false)
            ->assertSee('Unable to load resources', false)
            ->assertSee('Dev only', false);
    }

    public function test_showcase_returns_not_found_when_disabled(): void
    {
        config(['corepanel.ui.showcase.enabled' => false]);

        $this->get('/dev/components')
            ->assertNotFound();
    }

    public function test_showcase_defaults_to_local_environment_only(): void
    {
        config(['corepanel.ui.showcase.enabled' => null]);

        $this->app['env'] = 'production';

        $this->get('/dev/components')
            ->assertNotFound();

        $this->app['env'] = 'local';

        $this->get(route('dev.components'))
            ->assertOk()
            ->assertSee('Component showcase', false);
    }
}

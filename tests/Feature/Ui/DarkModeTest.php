<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DarkModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_script_applies_stored_preference_before_paint(): void
    {
        $html = Blade::render('<x-ui.theme-script />');

        $this->assertStringContainsString('corepanel.theme', $html);
        $this->assertStringContainsString('localStorage.getItem', $html);
        $this->assertStringContainsString("classList.toggle('dark'", $html);
        $this->assertStringContainsString('prefers-color-scheme', $html);
    }

    public function test_theme_toggle_renders_light_dark_and_system_controls(): void
    {
        $html = Blade::render('<x-ui.theme-toggle />');

        $this->assertStringContainsString('x-data="themeToggle"', $html);
        $this->assertStringContainsString(__('Light'), $html);
        $this->assertStringContainsString(__('Dark'), $html);
        $this->assertStringContainsString(__('System'), $html);
        $this->assertStringContainsString("set('dark')", $html);
        $this->assertStringContainsString('aria-pressed', $html);
    }

    public function test_theme_toggle_cycle_variant_renders(): void
    {
        $html = Blade::render('<x-ui.theme-toggle variant="cycle" />');

        $this->assertStringContainsString('cycle()', $html);
        $this->assertStringContainsString(__('Theme'), $html);
    }

    public function test_app_layout_includes_theme_script_and_dashboard_shows_toggle(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('corepanel.theme', false)
            ->assertSee('themeToggle', false)
            ->assertSee(__('Light'), false)
            ->assertSee(__('Dark'), false)
            ->assertSee(__('System'), false);
    }
}

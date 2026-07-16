<?php

namespace Tests\Feature\Client;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ClientMobileSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_layout_renders_mobile_sidebar_drawer_controls(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-layout.client title="Dashboard" page-heading="Dashboard">
                Page body
            </x-layout.client>
        BLADE);

        $this->assertStringContainsString('data-client-sidebar-toggle', $html);
        $this->assertStringContainsString('data-client-sidebar-overlay', $html);
        $this->assertStringContainsString('data-client-sidebar-drawer', $html);
        $this->assertStringContainsString('data-client-sidebar-close', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window="sidebarOpen = false"', $html);
        $this->assertStringContainsString('x-bind:aria-expanded="sidebarOpen"', $html);
    }

    public function test_custom_sidebar_slot_is_rendered_in_desktop_and_mobile_drawers(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-layout.client title="Custom">
                <x-slot:sidebar>
                    <nav aria-label="Custom client navigation">Custom drawer nav</nav>
                </x-slot:sidebar>
                Body
            </x-layout.client>
        BLADE);

        $this->assertSame(2, substr_count($html, 'Custom drawer nav'));
        $this->assertStringContainsString('data-client-sidebar-desktop', $html);
        $this->assertStringContainsString('data-client-sidebar-drawer', $html);
    }
}


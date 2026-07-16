<?php

namespace Tests\Feature\Client;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ClientLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_layout_renders_shell_regions(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-layout.client title="Services" page-heading="Services">
                <x-slot:subtitle>Manage your services</x-slot:subtitle>
                <x-slot:sidebar>
                    <nav aria-label="Client navigation">Sidebar nav</nav>
                </x-slot:sidebar>
                <x-slot:topbar>Top actions</x-slot:topbar>
                <x-slot:breadcrumbs>
                    <x-ui.breadcrumb :items="[
                        ['label' => 'Client', 'url' => '/client'],
                        ['label' => 'Services'],
                    ]" />
                </x-slot:breadcrumbs>
                Page body
            </x-layout.client>
        BLADE);

        $this->assertStringContainsString('Client navigation', $html);
        $this->assertStringContainsString('Client top bar', $html);
        $this->assertStringContainsString('Sidebar nav', $html);
        $this->assertStringContainsString('Top actions', $html);
        $this->assertStringContainsString('aria-label="Breadcrumb"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('Services', $html);
        $this->assertStringContainsString('Manage your services', $html);
        $this->assertStringContainsString('Page body', $html);
        $this->assertStringContainsString('w-64', $html);
        $this->assertStringContainsString('data-client-sidebar-desktop', $html);
        $this->assertStringContainsString('· Client', $html);
    }

    public function test_client_layout_brand_links_to_dashboard_fallback_without_client_route(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-layout.client title="Overview">
                Overview body
            </x-layout.client>
        BLADE);

        $this->assertStringContainsString('href="'.route('client.dashboard').'"', $html);
        $this->assertStringNotContainsString('data-client-sidebar-toggle', $html);
        $this->assertStringNotContainsString('data-client-sidebar-drawer', $html);
    }

    public function test_breadcrumb_marks_last_item_as_current_in_client_layout(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-layout.client title="Billing">
                <x-slot:breadcrumbs>
                    <x-ui.breadcrumb :items="[
                        ['label' => 'Client', 'url' => '/client'],
                        ['label' => 'Billing', 'url' => '/client/billing'],
                        ['label' => 'Invoices'],
                    ]" />
                </x-slot:breadcrumbs>
                Billing body
            </x-layout.client>
        BLADE);

        $this->assertStringContainsString('href="/client"', $html);
        $this->assertStringContainsString('href="/client/billing"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('Invoices', $html);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*"[^>]*>Invoices</', $html);
    }
}

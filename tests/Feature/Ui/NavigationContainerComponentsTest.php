<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class NavigationContainerComponentsTest extends TestCase
{
    public function test_card_renders_title_slots_and_content(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.card title="Services">
                <x-slot:actions>
                    <button type="button">Add</button>
                </x-slot:actions>
                Card body
                <x-slot:footer>
                    Footer actions
                </x-slot:footer>
            </x-ui.card>
        BLADE);

        $this->assertStringContainsString('Services', $html);
        $this->assertStringContainsString('Card body', $html);
        $this->assertStringContainsString('Footer actions', $html);
        $this->assertStringContainsString('Add', $html);
        $this->assertStringContainsString('border-border', $html);
    }

    public function test_modal_listens_for_open_events(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.modal name="confirm-delete" title="Delete item">
                Are you sure?
                <x-slot:footer>
                    <button type="button">Confirm</button>
                </x-slot:footer>
            </x-ui.modal>
        BLADE);

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('Delete item', $html);
        $this->assertStringContainsString('Are you sure?', $html);
        $this->assertStringContainsString('open-modal.window', $html);
        $this->assertStringContainsString('confirm-delete', $html);
        $this->assertStringContainsString('Confirm', $html);
    }

    public function test_dropdown_and_items_render_menu_structure(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.dropdown align="right">
                <x-slot:trigger>
                    <button type="button">Menu</button>
                </x-slot:trigger>
                <x-ui.dropdown-item href="/account">Profile</x-ui.dropdown-item>
                <x-ui.dropdown-item type="button">Sign out</x-ui.dropdown-item>
            </x-ui.dropdown>
        BLADE);

        $this->assertStringContainsString('Menu', $html);
        $this->assertStringContainsString('role="menu"', $html);
        $this->assertStringContainsString('role="menuitem"', $html);
        $this->assertStringContainsString('Profile', $html);
        $this->assertStringContainsString('Sign out', $html);
        $this->assertStringContainsString('click.outside', $html);
    }

    public function test_tabs_render_tablist_and_panels(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.tabs
                :items="[
                    ['name' => 'overview', 'label' => 'Overview'],
                    ['name' => 'settings', 'label' => 'Settings'],
                ]"
                active="overview"
            >
                <x-slot:overview>Overview content</x-slot:overview>
                <x-slot:settings>Settings content</x-slot:settings>
            </x-ui.tabs>
        BLADE);

        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('role="tab"', $html);
        $this->assertStringContainsString('role="tabpanel"', $html);
        $this->assertStringContainsString('Overview', $html);
        $this->assertStringContainsString('Overview content', $html);
        $this->assertStringContainsString('Settings content', $html);
    }
}

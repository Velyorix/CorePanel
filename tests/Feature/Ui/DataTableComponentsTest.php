<?php

namespace Tests\Feature\Ui;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DataTableComponentsTest extends TestCase
{
    public function test_table_renders_filters_actions_head_and_rows(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.table>
                <x-slot:filters>
                    <x-ui.input name="q" label="Search" />
                </x-slot:filters>
                <x-slot:actions>
                    <x-ui.button type="button" variant="danger" size="sm">Delete selected</x-ui.button>
                </x-slot:actions>
                <x-slot:head>
                    <tr>
                        <x-ui.table-heading sort="name">Name</x-ui.table-heading>
                        <x-ui.table-heading>Status</x-ui.table-heading>
                    </tr>
                </x-slot:head>
                <tr>
                    <td class="px-4 py-3">Acme</td>
                    <td class="px-4 py-3">Active</td>
                </tr>
            </x-ui.table>
        BLADE);

        $this->assertStringContainsString('Search', $html);
        $this->assertStringContainsString('Delete selected', $html);
        $this->assertStringContainsString('Name', $html);
        $this->assertStringContainsString('sort=name', $html);
        $this->assertStringContainsString('Acme', $html);
        $this->assertStringContainsString('overflow-x-auto', $html);
    }

    public function test_table_heading_toggles_sort_direction(): void
    {
        request()->merge(['sort' => 'name', 'dir' => 'asc']);

        $html = Blade::render(<<<'BLADE'
            <x-ui.table-heading sort="name">Name</x-ui.table-heading>
        BLADE);

        $this->assertStringContainsString('aria-sort="ascending"', $html);
        $this->assertStringContainsString('dir=desc', $html);
        $this->assertStringContainsString('↑', $html);
    }

    public function test_table_renders_empty_slot_when_paginator_is_empty(): void
    {
        $paginator = new LengthAwarePaginator(
            items: [],
            total: 0,
            perPage: 10,
            currentPage: 1,
            options: ['path' => '/clients'],
        );

        $html = Blade::render(<<<'BLADE'
            <x-ui.table :paginator="$paginator">
                <x-slot:head>
                    <tr><th>Name</th></tr>
                </x-slot:head>
                <x-slot:empty>
                    <tr>
                        <td class="px-4 py-8 text-center text-muted-foreground">No clients found.</td>
                    </tr>
                </x-slot:empty>
            </x-ui.table>
        BLADE, ['paginator' => $paginator]);

        $this->assertStringContainsString('No clients found.', $html);
        $this->assertStringNotContainsString('Pagination Navigation', $html);
    }

    public function test_pagination_view_renders_for_multi_page_results(): void
    {
        $paginator = new LengthAwarePaginator(
            items: range(1, 10),
            total: 35,
            perPage: 10,
            currentPage: 2,
            options: ['path' => '/clients', 'pageName' => 'page'],
        );

        $html = Blade::render(<<<'BLADE'
            <x-ui.table :paginator="$paginator">
                <tr><td class="px-4 py-3">Row</td></tr>
            </x-ui.table>
        BLADE, ['paginator' => $paginator]);

        $this->assertStringContainsString('Pagination Navigation', $html);
        $this->assertStringContainsString('Previous', $html);
        $this->assertStringContainsString('Next', $html);
        $this->assertStringContainsString('bg-primary-600', $html);
        $this->assertStringContainsString('Showing', $html);
    }
}

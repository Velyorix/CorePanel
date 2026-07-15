<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class StateComponentsTest extends TestCase
{
    public function test_skeleton_renders_loading_placeholder(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.skeleton />
            <x-ui.skeleton variant="circle" />
            <x-ui.skeleton variant="line" :lines="3" />
            <x-ui.skeleton variant="rect" class="h-32" />
        BLADE);

        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('animate-pulse', $html);
        $this->assertStringContainsString('bg-muted', $html);
        $this->assertStringContainsString('rounded-full', $html);
        $this->assertStringContainsString(__('Loading'), $html);
    }

    public function test_empty_state_renders_title_description_and_actions(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.empty title="No clients" description="Create your first client to get started.">
                <x-slot:actions>
                    <x-ui.button variant="primary">Create client</x-ui.button>
                </x-slot:actions>
            </x-ui.empty>
        BLADE);

        $this->assertStringContainsString('No clients', $html);
        $this->assertStringContainsString('Create your first client to get started.', $html);
        $this->assertStringContainsString('Create client', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('border-dashed', $html);
    }

    public function test_error_state_renders_message_code_and_actions(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.error-state
                title="Unable to load services"
                description="Please try again in a moment."
                code="503"
            >
                <x-slot:actions>
                    <x-ui.button variant="secondary" href="/dashboard">Back</x-ui.button>
                </x-slot:actions>
            </x-ui.error-state>
        BLADE);

        $this->assertStringContainsString('Unable to load services', $html);
        $this->assertStringContainsString('Please try again in a moment.', $html);
        $this->assertStringContainsString('503', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('Back', $html);
        $this->assertStringContainsString('border-danger-200', $html);
    }
}

<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class FormFeedbackComponentsTest extends TestCase
{
    public function test_button_variants_render(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.button variant="primary">Save</x-ui.button>
            <x-ui.button variant="secondary" type="submit">Continue</x-ui.button>
            <x-ui.button variant="danger">Delete</x-ui.button>
            <x-ui.button variant="ghost" disabled>Cancel</x-ui.button>
            <x-ui.button href="/dashboard" variant="primary">Open</x-ui.button>
        BLADE);

        $this->assertStringContainsString('Save', $html);
        $this->assertStringContainsString('bg-primary-600', $html);
        $this->assertStringContainsString('bg-danger-600', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('href="/dashboard"', $html);
        $this->assertStringContainsString('Open', $html);
    }

    public function test_input_and_select_render_labels_and_errors(): void
    {
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag([
            'email' => ['The email field is required.'],
            'role' => ['The role field is required.'],
        ]));

        view()->share('errors', $errors);

        $html = Blade::render(<<<'BLADE'
            <x-ui.input name="email" label="Email" type="email" hint="Work email" />
            <x-ui.select name="role" label="Role">
                <option value="">Choose</option>
                <option value="admin">Admin</option>
            </x-ui.select>
        BLADE);

        $this->assertStringContainsString('Email', $html);
        $this->assertStringContainsString('The email field is required.', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('Role', $html);
        $this->assertStringContainsString('The role field is required.', $html);
        $this->assertStringContainsString('<option value="admin">Admin</option>', $html);
        $this->assertStringNotContainsString('Work email', $html);
    }

    public function test_badge_and_alert_variants_render(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.badge variant="success">Active</x-ui.badge>
            <x-ui.badge variant="warning">Pending</x-ui.badge>
            <x-ui.alert variant="danger" title="Error">Unable to save changes.</x-ui.alert>
            <x-ui.alert variant="success">Saved.</x-ui.alert>
        BLADE);

        $this->assertStringContainsString('Active', $html);
        $this->assertStringContainsString('bg-success-50', $html);
        $this->assertStringContainsString('Error', $html);
        $this->assertStringContainsString('Unable to save changes.', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('Saved.', $html);
    }
}

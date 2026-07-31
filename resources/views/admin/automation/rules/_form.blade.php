@php
    /** @var \Core\Automation\Models\AutomationRule|null $rule */
    $rule ??= null;
    $pretty = static function (mixed $value): string {
        if ($value === null) {
            return '';
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    };
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <x-ui.input
        name="name"
        :label="__('Name')"
        :value="old('name', $rule?->name)"
        class="sm:col-span-2"
        required
    />

    <x-ui.input
        name="priority"
        type="number"
        :label="__('Priority')"
        :value="old('priority', $rule?->priority ?? 100)"
        min="0"
        :hint="__('Lower runs first.')"
    />

    <div class="flex items-center gap-2">
        <input
            id="is_active"
            type="checkbox"
            name="is_active"
            value="1"
            class="rounded border-border"
            @checked((bool) old('is_active', $rule?->is_active ?? true))
        >
        <label for="is_active" class="text-body-sm font-medium text-foreground">{{ __('Active') }}</label>
    </div>

    <div class="sm:col-span-2">
        <label for="condition_json" class="mb-1.5 block text-body-sm font-medium text-foreground">{{ __('Conditions (JSON)') }}</label>
        <textarea
            id="condition_json"
            name="condition_json"
            rows="8"
            required
            class="block w-full rounded-md border border-border bg-surface px-3 py-2 font-mono text-small text-foreground shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            placeholder='{"event":"invoice.overdue","all":[{"field":"days_overdue","operator":"gt","value":3}]}'
        >{{ old('condition_json', $pretty($rule?->condition_json ?? ['all' => []])) }}</textarea>
        @error('condition_json')
            <p class="mt-1.5 text-small text-danger-700 dark:text-danger-400" role="alert">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="action_json" class="mb-1.5 block text-body-sm font-medium text-foreground">{{ __('Action (JSON)') }}</label>
        <textarea
            id="action_json"
            name="action_json"
            rows="6"
            required
            class="block w-full rounded-md border border-border bg-surface px-3 py-2 font-mono text-small text-foreground shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            placeholder='{"type":"suspend_service","fallback":{"type":"manual_intervention"}}'
        >{{ old('action_json', $pretty($rule?->action_json ?? ['type' => 'noop'])) }}</textarea>
        @error('action_json')
            <p class="mt-1.5 text-small text-danger-700 dark:text-danger-400" role="alert">{{ $message }}</p>
        @enderror
    </div>
</div>

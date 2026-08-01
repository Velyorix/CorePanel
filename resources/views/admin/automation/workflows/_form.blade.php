@php
    /** @var \Core\Automation\Models\Workflow|null $workflow */
    $workflow ??= null;
    $pretty = static function (mixed $value): string {
        if ($value === null || $value === []) {
            return '';
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    };
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <x-ui.input
        name="name"
        :label="__('Name')"
        :value="old('name', $workflow?->name)"
        class="sm:col-span-2"
        required
    />

    <x-ui.input
        name="slug"
        :label="__('Slug')"
        :value="old('slug', $workflow?->slug)"
        :hint="__('Leave empty to generate from the name.')"
    />

    <x-ui.select name="trigger_event" :label="__('Trigger event')" required>
        @foreach ($events as $event)
            <option value="{{ $event }}" @selected((string) old('trigger_event', $workflow?->trigger_event) === $event)>
                {{ \Core\Automation\Support\AutomationEvent::label($event) }} ({{ $event }})
            </option>
        @endforeach
    </x-ui.select>

    <x-ui.input
        name="priority"
        type="number"
        :label="__('Priority')"
        :value="old('priority', $workflow?->priority ?? 100)"
        min="0"
        :hint="__('Lower runs first.')"
    />

    <div class="flex items-center gap-2 sm:col-span-2">
        <input
            id="is_active"
            type="checkbox"
            name="is_active"
            value="1"
            class="rounded border-border"
            @checked((bool) old('is_active', $workflow?->is_active ?? true))
        >
        <label for="is_active" class="text-body-sm font-medium text-foreground">{{ __('Active') }}</label>
    </div>

    <div class="sm:col-span-2">
        <label for="conditions_json" class="mb-1.5 block text-body-sm font-medium text-foreground">{{ __('Conditions (JSON)') }}</label>
        <textarea
            id="conditions_json"
            name="conditions_json"
            rows="6"
            class="block w-full rounded-md border border-border bg-surface px-3 py-2 font-mono text-small text-foreground shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            placeholder='{"all":[{"field":"amount","operator":"gte","value":10}]}'
        >{{ old('conditions_json', $pretty($workflow?->conditions)) }}</textarea>
        @error('conditions_json')
            <p class="mt-1.5 text-small text-danger-700 dark:text-danger-400" role="alert">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="steps_json" class="mb-1.5 block text-body-sm font-medium text-foreground">{{ __('Steps (JSON array)') }}</label>
        <textarea
            id="steps_json"
            name="steps_json"
            rows="8"
            required
            class="block w-full rounded-md border border-border bg-surface px-3 py-2 font-mono text-small text-foreground shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            placeholder='[{"type":"log","message":"handled"}]'
        >{{ old('steps_json', $pretty($workflow?->steps ?? [['type' => 'noop']])) }}</textarea>
        @error('steps_json')
            <p class="mt-1.5 text-small text-danger-700 dark:text-danger-400" role="alert">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="fallback_json" class="mb-1.5 block text-body-sm font-medium text-foreground">{{ __('Fallback (JSON)') }}</label>
        <textarea
            id="fallback_json"
            name="fallback_json"
            rows="4"
            class="block w-full rounded-md border border-border bg-surface px-3 py-2 font-mono text-small text-foreground shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            placeholder='{"type":"manual_intervention","message":"Needs review"}'
        >{{ old('fallback_json', $pretty($workflow?->fallback)) }}</textarea>
        @error('fallback_json')
            <p class="mt-1.5 text-small text-danger-700 dark:text-danger-400" role="alert">{{ $message }}</p>
        @enderror
    </div>
</div>

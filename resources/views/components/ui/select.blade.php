@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'hint' => null,
    'error' => null,
])

@php
    $selectId = $id ?? $name;
    $errorMessage = $error ?? (filled($name) ? $errors->first($name) : null);
    $describedBy = collect([
        filled($hint) && filled($selectId) ? $selectId.'-hint' : null,
        filled($errorMessage) && filled($selectId) ? $selectId.'-error' : null,
    ])->filter()->implode(' ') ?: null;
@endphp

<div {{ $attributes->only('class')->class('w-full') }}>
    @if (filled($label))
        <label
            @if (filled($selectId)) for="{{ $selectId }}" @endif
            class="mb-1.5 block text-body-sm font-medium text-foreground"
        >
            {{ $label }}
        </label>
    @endif

    <select
        @if (filled($name)) name="{{ $name }}" @endif
        @if (filled($selectId)) id="{{ $selectId }}" @endif
        @if (filled($describedBy)) aria-describedby="{{ $describedBy }}" @endif
        @if (filled($errorMessage)) aria-invalid="true" @endif
        {{ $attributes->except('class')->class([
            'block w-full rounded-md border bg-surface px-3 py-2 text-body-sm text-foreground shadow-sm transition',
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
            'disabled:cursor-not-allowed disabled:bg-muted disabled:opacity-60',
            filled($errorMessage)
                ? 'border-danger-300 focus-visible:outline-danger-600'
                : 'border-border',
        ]) }}
    >
        {{ $slot }}
    </select>

    @if (filled($hint) && blank($errorMessage))
        <p @if (filled($selectId)) id="{{ $selectId }}-hint" @endif class="mt-1.5 text-small text-muted-foreground">
            {{ $hint }}
        </p>
    @endif

    @if (filled($errorMessage))
        <p @if (filled($selectId)) id="{{ $selectId }}-error" @endif class="mt-1.5 text-small text-danger-700 dark:text-danger-400" role="alert">
            {{ $errorMessage }}
        </p>
    @endif
</div>

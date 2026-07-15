@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'type' => 'text',
    'hint' => null,
    'error' => null,
])

@php
    $inputId = $id ?? $name;
    $errorMessage = $error ?? (filled($name) ? $errors->first($name) : null);
    $describedBy = collect([
        filled($hint) && filled($inputId) ? $inputId.'-hint' : null,
        filled($errorMessage) && filled($inputId) ? $inputId.'-error' : null,
    ])->filter()->implode(' ') ?: null;
@endphp

<div {{ $attributes->only('class')->class('w-full') }}>
    @if (filled($label))
        <label
            @if (filled($inputId)) for="{{ $inputId }}" @endif
            class="mb-1.5 block text-body-sm font-medium text-foreground"
        >
            {{ $label }}
        </label>
    @endif

    <input
        type="{{ $type }}"
        @if (filled($name)) name="{{ $name }}" @endif
        @if (filled($inputId)) id="{{ $inputId }}" @endif
        @if (filled($describedBy)) aria-describedby="{{ $describedBy }}" @endif
        @if (filled($errorMessage)) aria-invalid="true" @endif
        {{ $attributes->except('class')->class([
            'block w-full rounded-md border bg-surface px-3 py-2 text-body-sm text-foreground shadow-sm transition',
            'placeholder:text-muted-foreground',
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
            'disabled:cursor-not-allowed disabled:bg-muted disabled:opacity-60',
            filled($errorMessage)
                ? 'border-danger-300 focus-visible:outline-danger-600'
                : 'border-border',
        ]) }}
    >

    @if (filled($hint) && blank($errorMessage))
        <p @if (filled($inputId)) id="{{ $inputId }}-hint" @endif class="mt-1.5 text-small text-muted-foreground">
            {{ $hint }}
        </p>
    @endif

    @if (filled($errorMessage))
        <p @if (filled($inputId)) id="{{ $inputId }}-error" @endif class="mt-1.5 text-small text-danger-700 dark:text-danger-400" role="alert">
            {{ $errorMessage }}
        </p>
    @endif
</div>

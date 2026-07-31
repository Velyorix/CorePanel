@props([
    'title' => null,
    'description' => null,
    'code' => null,
])

<div
    {{ $attributes->class('flex flex-col items-center justify-center rounded-lg border border-danger-200 bg-danger-50 px-6 py-12 text-center dark:border-danger-900 dark:bg-danger-950') }}
    role="alert"
>
    @isset($icon)
        <div class="mb-4 text-danger-600 dark:text-danger-400">
            {{ $icon }}
        </div>
    @else
        <div
            class="mb-4 flex size-12 items-center justify-center rounded-full bg-danger-100 text-danger-700 dark:bg-danger-900 dark:text-danger-300"
            aria-hidden="true"
        >
            <span class="text-h3 font-semibold">!</span>
        </div>
    @endisset

    @if (filled($code))
        <p class="mb-2 text-small font-medium uppercase tracking-wide text-danger-700 dark:text-danger-300">
            {{ __('Error :code', ['code' => $code]) }}
        </p>
    @endif

    <h3 class="text-h3 font-semibold tracking-tight text-danger-950 dark:text-danger-50">
        {{ $title ?? __('Something went wrong') }}
    </h3>

    @if (filled($description))
        <p class="mt-2 max-w-md text-body-sm text-danger-800 dark:text-danger-200">
            {{ $description }}
        </p>
    @endif

    @if (trim((string) $slot) !== '')
        <div class="mt-2 max-w-md text-body-sm text-danger-800 dark:text-danger-200">
            {{ $slot }}
        </div>
    @endif

    @isset($actions)
        <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>

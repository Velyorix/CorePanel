@php
    $impersonation = app(\Core\Clients\Services\ClientImpersonationService::class);
@endphp

@if ($impersonation->isImpersonating())
    <div class="border-b border-warning-200 bg-warning-50 px-4 py-3 text-body-sm text-warning-800 dark:border-warning-900 dark:bg-warning-950 dark:text-warning-200 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p>
                {{ __('You are impersonating :name (:email). Actions are audited.', [
                    'name' => auth()->user()?->name,
                    'email' => auth()->user()?->email,
                ]) }}
            </p>
            <form method="POST" action="{{ route('impersonation.leave') }}">
                @csrf
                <x-ui.button type="submit" variant="secondary" size="sm">
                    {{ __('Leave impersonation') }}
                </x-ui.button>
            </form>
        </div>
    </div>
@endif

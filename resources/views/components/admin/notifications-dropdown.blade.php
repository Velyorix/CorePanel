@php
    use Core\Admin\Notifications\AdminNotificationFeed;

    $feed = app(AdminNotificationFeed::class);
    $notifications = $feed->placeholders();
    $unreadCount = $feed->unreadCount();
@endphp

<x-ui.dropdown align="right" panel-class="min-w-80 max-w-sm py-0">
    <x-slot:trigger>
        <button
            type="button"
            class="relative inline-flex size-9 items-center justify-center rounded-md border border-border bg-surface text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            aria-label="{{ __('Notifications') }}"
        >
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>

            @if ($unreadCount > 0)
                <span
                    class="absolute -end-0.5 -top-0.5 inline-flex min-w-5 items-center justify-center rounded-full bg-danger-600 px-1 text-[10px] font-semibold leading-4 text-white"
                    data-unread-count="{{ $unreadCount }}"
                >
                    {{ $unreadCount }}
                </span>
            @endif
        </button>
    </x-slot:trigger>

    <div class="border-b border-border px-4 py-3">
        <p class="text-body-sm font-semibold text-foreground">{{ __('Notifications') }}</p>
        <p class="mt-0.5 text-small text-muted-foreground">
            {{ __('Placeholder feed until the notification center is connected.') }}
        </p>
    </div>

    <div class="max-h-80 overflow-y-auto" role="list" aria-label="{{ __('Recent notifications') }}">
        @foreach ($notifications as $notification)
            <div
                class="border-b border-border px-4 py-3 last:border-b-0"
                role="listitem"
                data-notification="{{ $notification['key'] }}"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-body-sm font-medium text-foreground">{{ $notification['title'] }}</p>
                        <p class="mt-1 text-small text-muted-foreground">{{ $notification['message'] }}</p>
                    </div>
                    <x-ui.badge :variant="$notification['variant']" class="shrink-0">
                        {{ $notification['unread'] ? __('New') : __('Read') }}
                    </x-ui.badge>
                </div>
                <p class="mt-2 text-small text-muted-foreground">{{ $notification['time'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="border-t border-border px-4 py-3 text-center">
        <p class="text-small text-muted-foreground">
            {{ __('View all notifications') }}
            <span class="text-muted-foreground/80">· {{ __('Coming soon') }}</span>
        </p>
    </div>
</x-ui.dropdown>

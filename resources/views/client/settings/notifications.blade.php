<x-layout.client
    :title="__('Notifications')"
    :page-heading="__('Notifications')"
>
    <x-slot:subtitle>
        {{ __('Choose which channels and topics you want to hear about.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <div class="ml-auto flex items-center gap-3">
            <x-ui.theme-toggle />
            @auth
                <span class="hidden text-body-sm text-muted-foreground sm:inline">
                    {{ auth()->user()->email }}
                </span>
            @endauth
        </div>
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Client'), 'url' => route('client.dashboard')],
            ['label' => __('Settings')],
            ['label' => __('Notifications')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        @endif

        @if ($errors->any())
            <x-ui.alert variant="danger" :title="__('Unable to continue')">
                <ul class="list-disc ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <form method="POST" action="{{ route('client.settings.notifications.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-ui.card :title="__('Channels')">
                <p class="mb-4 text-body-sm text-muted-foreground">
                    {{ __('Master switches applied to all notification topics. Security emails cannot be disabled.') }}
                </p>

                <div class="space-y-3">
                    @foreach ($channels as $channel)
                        <label class="flex items-center gap-2 text-body-sm text-foreground">
                            <input
                                type="checkbox"
                                name="channels[{{ $channel }}]"
                                value="1"
                                class="rounded border-border"
                                @checked(old("channels.{$channel}", $preferences['channels'][$channel] ?? true))
                            >
                            {{ $channel === 'mail' ? __('Email') : __('Dashboard') }}
                        </label>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Topics')">
                <p class="mb-4 text-body-sm text-muted-foreground">
                    {{ __('Fine-tune delivery per topic. A topic only sends on channels that are also enabled above.') }}
                </p>

                <div class="space-y-6">
                    @foreach ($categories as $category)
                        @php
                            $categoryLabel = match ($category) {
                                'billing' => __('Billing'),
                                'tickets' => __('Tickets'),
                                'services' => __('Services'),
                                default => ucfirst($category),
                            };
                        @endphp

                        <div>
                            <h3 class="mb-3 text-body-sm font-medium text-foreground">{{ $categoryLabel }}</h3>
                            <div class="flex flex-wrap gap-4">
                                @foreach ($channels as $channel)
                                    <label class="flex items-center gap-2 text-body-sm text-foreground">
                                        <input
                                            type="checkbox"
                                            name="categories[{{ $category }}][{{ $channel }}]"
                                            value="1"
                                            class="rounded border-border"
                                            @checked(old(
                                                "categories.{$category}.{$channel}",
                                                $preferences['categories'][$category][$channel] ?? true
                                            ))
                                        >
                                        {{ $channel === 'mail' ? __('Email') : __('Dashboard') }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            <div class="flex justify-end">
                <x-ui.button type="submit" variant="primary">
                    {{ __('Save preferences') }}
                </x-ui.button>
            </div>
        </form>
    </div>
</x-layout.client>

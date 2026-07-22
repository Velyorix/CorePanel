<x-layout.admin
    :title="$theme['label']"
    :page-heading="$theme['label']"
>
    <x-slot:subtitle>
        {{ __('Theme package details and lifecycle actions.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Themes'), 'url' => route('admin.themes.index')],
            ['label' => $theme['label']],
        ]" />
    </x-slot:breadcrumbs>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6">
            <x-ui.alert variant="danger" :title="__('Unable to continue')">
                <ul class="list-disc ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-center gap-2">
        <x-ui.button :href="route('admin.themes.index')" variant="secondary" size="sm">
            {{ __('Back to themes') }}
        </x-ui.button>

        @if ($canManage)
            @unless ($theme['is_active'])
                <form method="POST" action="{{ route('admin.themes.activate', $theme['key']) }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Activate globally') }}
                    </x-ui.button>
                </form>
            @endunless

            @unless ($theme['is_preview'])
                <form method="POST" action="{{ route('admin.themes.preview', $theme['key']) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">
                        {{ __('Preview in session') }}
                    </x-ui.button>
                </form>
            @endunless
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="grid gap-4 text-small">
                <div>
                    <dt class="font-medium text-muted-foreground">{{ __('Key') }}</dt>
                    <dd class="mt-1 font-mono">{{ $theme['key'] }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-muted-foreground">{{ __('Version') }}</dt>
                    <dd class="mt-1 font-mono">{{ $theme['version'] }}</dd>
                </div>
                @if (filled($theme['author']))
                    <div>
                        <dt class="font-medium text-muted-foreground">{{ __('Author') }}</dt>
                        <dd class="mt-1">{{ $theme['author'] }}</dd>
                    </div>
                @endif
                @if (filled($theme['parent']))
                    <div>
                        <dt class="font-medium text-muted-foreground">{{ __('Parent theme') }}</dt>
                        <dd class="mt-1 font-mono">{{ $theme['parent'] }}</dd>
                    </div>
                @endif
                @if (filled($theme['description']))
                    <div>
                        <dt class="font-medium text-muted-foreground">{{ __('Description') }}</dt>
                        <dd class="mt-1">{{ $theme['description'] }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="font-medium text-muted-foreground">{{ __('Package path') }}</dt>
                    <dd class="mt-1 break-all font-mono text-xs">{{ $theme['path'] }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Runtime')">
            <dl class="grid gap-4 text-small">
                <div>
                    <dt class="font-medium text-muted-foreground">{{ __('Status') }}</dt>
                    <dd class="mt-2 flex flex-wrap gap-1.5">
                        @if ($theme['is_active'])
                            <x-ui.badge variant="success">{{ __('Active globally') }}</x-ui.badge>
                        @endif
                        @if ($theme['is_preview'])
                            <x-ui.badge variant="primary">{{ __('Preview session') }}</x-ui.badge>
                        @endif
                        @if ($theme['is_loaded'])
                            <x-ui.badge variant="secondary">{{ __('Loaded') }}</x-ui.badge>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-muted-foreground">{{ __('Views directory') }}</dt>
                    <dd class="mt-1 break-all font-mono text-xs">
                        {{ $theme['has_views'] ? $theme['views_path'] : __('Not present') }}
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-muted-foreground">{{ __('Asset entries') }}</dt>
                    <dd class="mt-1">
                        @if ($theme['asset_entries'] === [])
                            <span class="text-muted-foreground">{{ __('None declared') }}</span>
                        @else
                            <ul class="list-disc ps-4 font-mono text-xs">
                                @foreach ($theme['asset_entries'] as $entry)
                                    <li>{{ $entry }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-ui.card>
    </div>
</x-layout.admin>

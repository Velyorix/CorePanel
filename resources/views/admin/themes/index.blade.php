<x-layout.admin
    :title="__('Themes')"
    :page-heading="__('Themes')"
>
    <x-slot:subtitle>
        {{ __('Discover, activate, and preview CorePanel themes.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Themes')],
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

    @if ($previewKey !== null)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-primary/30 bg-primary/5 px-4 py-3">
            <div class="text-small">
                {{ __('Previewing theme :theme in your session.', ['theme' => $previewKey]) }}
            </div>
            @if ($canManage)
                <form method="POST" action="{{ route('admin.themes.preview.clear') }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">
                        {{ __('Clear preview') }}
                    </x-ui.button>
                </form>
            @endif
        </div>
    @endif

    <x-ui.table>
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 font-medium">{{ __('Theme') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Version') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Resources') }}</th>
                <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
            </tr>
        </x-slot:head>

        @forelse ($themes as $theme)
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.themes.show', $theme['key']) }}" class="font-medium text-primary hover:underline">
                        {{ $theme['label'] }}
                    </a>
                    <div class="mt-0.5 font-mono text-small text-muted-foreground">{{ $theme['key'] }}</div>
                    @if (filled($theme['description']))
                        <div class="mt-1 max-w-md text-small text-muted-foreground">{{ $theme['description'] }}</div>
                    @endif
                    @if (filled($theme['author']))
                        <div class="mt-1 text-small text-muted-foreground">{{ $theme['author'] }}</div>
                    @endif
                </td>
                <td class="px-4 py-3 font-mono text-small">{{ $theme['version'] }}</td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-1.5">
                        @if ($theme['is_active'])
                            <x-ui.badge variant="success">{{ __('Active') }}</x-ui.badge>
                        @endif

                        @if ($theme['is_preview'])
                            <x-ui.badge variant="primary">{{ __('Preview') }}</x-ui.badge>
                        @endif

                        @if (! $theme['is_active'] && ! $theme['is_preview'])
                            <x-ui.badge variant="neutral">{{ __('Available') }}</x-ui.badge>
                        @endif

                        @if ($theme['is_loaded'])
                            <x-ui.badge variant="secondary">{{ __('Loaded') }}</x-ui.badge>
                        @endif
                    </div>
                </td>
                <td class="px-4 py-3 text-small text-muted-foreground">
                    <div class="flex flex-wrap gap-1.5">
                        @if ($theme['has_views'])
                            <x-ui.badge variant="neutral">{{ __('Views') }}</x-ui.badge>
                        @endif
                        @if ($theme['has_assets'])
                            <x-ui.badge variant="neutral">{{ __('Assets') }}</x-ui.badge>
                        @endif
                        @if (! $theme['has_views'] && ! $theme['has_assets'])
                            —
                        @endif
                    </div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-ui.button :href="route('admin.themes.show', $theme['key'])" variant="ghost" size="sm">
                            {{ __('View') }}
                        </x-ui.button>

                        @if ($canManage)
                            @unless ($theme['is_active'])
                                <form method="POST" action="{{ route('admin.themes.activate', $theme['key']) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="primary" size="sm">
                                        {{ __('Activate') }}
                                    </x-ui.button>
                                </form>
                            @endunless

                            @unless ($theme['is_preview'])
                                <form method="POST" action="{{ route('admin.themes.preview', $theme['key']) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm">
                                        {{ __('Preview') }}
                                    </x-ui.button>
                                </form>
                            @endunless
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                    {{ __('No themes discovered in the themes directory.') }}
                </td>
            </tr>
        @endforelse
    </x-ui.table>
</x-layout.admin>

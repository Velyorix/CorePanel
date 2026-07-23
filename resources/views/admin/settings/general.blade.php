<x-layout.admin
    :title="__('General')"
    :page-heading="__('General')"
>
    <x-slot:subtitle>
        {{ __('Site name, default locale, and timezone.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Settings')],
            ['label' => __('General')],
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

        <x-ui.card :title="__('General settings')">
            @if ($canManage)
                <form method="POST" action="{{ route('admin.settings.general.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <x-ui.input
                        name="site_name"
                        :label="__('Site name')"
                        :value="old('site_name', $values['site_name'])"
                        required
                    />

                    <x-ui.select name="locale" :label="__('Locale')">
                        @foreach ($locales as $locale)
                            <option value="{{ $locale }}" @selected(old('locale', $values['locale']) === $locale)>
                                {{ strtoupper($locale) }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select name="timezone" :label="__('Timezone')">
                        @foreach ($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', $values['timezone']) === $timezone)>
                                {{ $timezone }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Save settings') }}
                    </x-ui.button>
                </form>
            @else
                <dl class="space-y-3 text-body-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Site name') }}</dt>
                        <dd class="font-medium">{{ $values['site_name'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Locale') }}</dt>
                        <dd class="font-medium">{{ strtoupper($values['locale']) }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Timezone') }}</dt>
                        <dd class="font-medium">{{ $values['timezone'] }}</dd>
                    </div>
                </dl>
            @endif
        </x-ui.card>
    </div>
</x-layout.admin>

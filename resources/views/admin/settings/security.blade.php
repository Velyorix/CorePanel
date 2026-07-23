<x-layout.admin
    :title="__('Security')"
    :page-heading="__('Security')"
>
    <x-slot:subtitle>
        {{ __('Password policy, two-factor requirement, and session lifetime.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Settings')],
            ['label' => __('Security')],
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

        <x-ui.card :title="__('Security settings')">
            @if ($canManage)
                <form method="POST" action="{{ route('admin.settings.security.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <label class="flex items-center gap-2 text-body-sm text-foreground">
                        <input
                            type="checkbox"
                            name="two_factor_required"
                            value="1"
                            class="rounded border-border"
                            @checked(old('two_factor_required', $values['two_factor_required']))
                        >
                        {{ __('Require two-factor authentication') }}
                    </label>

                    <x-ui.input
                        name="password_min_length"
                        type="number"
                        min="8"
                        max="128"
                        :label="__('Minimum password length')"
                        :value="old('password_min_length', $values['password_min_length'])"
                        required
                    />

                    <label class="flex items-center gap-2 text-body-sm text-foreground">
                        <input
                            type="checkbox"
                            name="password_require_special"
                            value="1"
                            class="rounded border-border"
                            @checked(old('password_require_special', $values['password_require_special']))
                        >
                        {{ __('Require a special character') }}
                    </label>

                    <label class="flex items-center gap-2 text-body-sm text-foreground">
                        <input
                            type="checkbox"
                            name="password_check_compromised"
                            value="1"
                            class="rounded border-border"
                            @checked(old('password_check_compromised', $values['password_check_compromised']))
                        >
                        {{ __('Reject compromised passwords') }}
                    </label>

                    <x-ui.input
                        name="session_timeout_minutes"
                        type="number"
                        min="5"
                        max="10080"
                        :label="__('Session timeout (minutes)')"
                        :value="old('session_timeout_minutes', $values['session_timeout_minutes'])"
                        required
                    />

                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Save settings') }}
                    </x-ui.button>
                </form>
            @else
                <dl class="space-y-3 text-body-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Two-factor required') }}</dt>
                        <dd class="font-medium">{{ $values['two_factor_required'] ? __('Yes') : __('No') }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Minimum password length') }}</dt>
                        <dd class="font-medium">{{ $values['password_min_length'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Special character required') }}</dt>
                        <dd class="font-medium">{{ $values['password_require_special'] ? __('Yes') : __('No') }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Compromised password check') }}</dt>
                        <dd class="font-medium">{{ $values['password_check_compromised'] ? __('Yes') : __('No') }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Session timeout (minutes)') }}</dt>
                        <dd class="font-medium">{{ $values['session_timeout_minutes'] }}</dd>
                    </div>
                </dl>
            @endif
        </x-ui.card>
    </div>
</x-layout.admin>

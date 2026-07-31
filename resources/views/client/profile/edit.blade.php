<x-layout.client
    :title="__('Profile')"
    :page-heading="__('Profile')"
>
    <x-slot:subtitle>
        {{ __('Update your account details and password.') }}
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
            ['label' => __('Profile')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Profile information')">
            @if (session('status'))
                <div class="mb-4">
                    <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
                </div>
            @endif

            <form method="POST" action="{{ route('client.profile.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <x-ui.input
                    name="name"
                    :label="__('Name')"
                    :value="old('name', $user->name)"
                    required
                    autocomplete="name"
                />

                <x-ui.input
                    name="email"
                    type="email"
                    :label="__('Email')"
                    :value="old('email', $user->email)"
                    required
                    autocomplete="email"
                />

                <div class="flex justify-end pt-2">
                    <x-ui.button type="submit" variant="primary">
                        {{ __('Save profile') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card :title="__('Change password')">
            @if (session('password_status'))
                <div class="mb-4">
                    <x-ui.alert variant="success">{{ session('password_status') }}</x-ui.alert>
                </div>
            @endif

            <form method="POST" action="{{ route('client.profile.password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <x-ui.input
                    name="current_password"
                    type="password"
                    :label="__('Current password')"
                    required
                    autocomplete="current-password"
                />

                <x-ui.input
                    name="password"
                    type="password"
                    :label="__('New password')"
                    required
                    autocomplete="new-password"
                />

                <x-ui.input
                    name="password_confirmation"
                    type="password"
                    :label="__('Confirm new password')"
                    required
                    autocomplete="new-password"
                />

                <div class="flex justify-end pt-2">
                    <x-ui.button type="submit" variant="primary">
                        {{ __('Update password') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-layout.client>


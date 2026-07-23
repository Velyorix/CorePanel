<x-layout.admin
    :title="__('Mail')"
    :page-heading="__('Mail')"
>
    <x-slot:subtitle>
        {{ __('SMTP transport and default sender identity.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Settings')],
            ['label' => __('Mail')],
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

        <x-ui.card :title="__('Mail settings')">
            @if ($canManage)
                <form method="POST" action="{{ route('admin.settings.mail.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.input
                            name="host"
                            :label="__('SMTP host')"
                            :value="old('host', $values['host'])"
                            required
                        />
                        <x-ui.input
                            name="port"
                            type="number"
                            min="1"
                            max="65535"
                            :label="__('Port')"
                            :value="old('port', $values['port'])"
                            required
                        />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.input
                            name="username"
                            :label="__('Username')"
                            :value="old('username', $values['username'])"
                        />
                        <x-ui.input
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            :label="__('Password')"
                            :hint="$passwordConfigured
                                ? __('Leave blank to keep the current password.')
                                : __('Optional SMTP password.')"
                        />
                    </div>

                    <x-ui.select name="encryption" :label="__('Encryption')">
                        @foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => __('None')] as $value => $label)
                            <option value="{{ $value }}" @selected(old('encryption', $values['encryption']) === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.input
                            name="from_address"
                            type="email"
                            :label="__('From address')"
                            :value="old('from_address', $values['from_address'])"
                            required
                        />
                        <x-ui.input
                            name="from_name"
                            :label="__('From name')"
                            :value="old('from_name', $values['from_name'])"
                            required
                        />
                    </div>

                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Save settings') }}
                    </x-ui.button>
                </form>
            @else
                <dl class="space-y-3 text-body-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('SMTP host') }}</dt>
                        <dd class="font-medium">{{ $values['host'] ?: '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Port') }}</dt>
                        <dd class="font-medium">{{ $values['port'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Username') }}</dt>
                        <dd class="font-medium">{{ $values['username'] ?: '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Password') }}</dt>
                        <dd class="font-medium">{{ $passwordConfigured ? __('Configured') : __('Not set') }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Encryption') }}</dt>
                        <dd class="font-medium">{{ strtoupper($values['encryption']) }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('From address') }}</dt>
                        <dd class="font-medium">{{ $values['from_address'] ?: '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('From name') }}</dt>
                        <dd class="font-medium">{{ $values['from_name'] ?: '—' }}</dd>
                    </div>
                </dl>
            @endif
        </x-ui.card>
    </div>
</x-layout.admin>

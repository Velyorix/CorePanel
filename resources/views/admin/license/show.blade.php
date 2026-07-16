<x-layout.admin
    :title="__('License')"
    :page-heading="__('License')"
>
    <x-slot:subtitle>
        {{ __('CorePanel.org license status, entitlements, and activation key.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Settings')],
            ['label' => __('License')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        @endif

        @if (session('error'))
            <x-ui.alert variant="danger">{{ session('error') }}</x-ui.alert>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card :title="__('License status')">
                <dl class="space-y-3 text-body-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                        <dd>
                            @php
                                $statusVariant = match (true) {
                                    $state->isValid && $state->inGracePeriod => 'warning',
                                    $state->isValid => 'success',
                                    default => 'danger',
                                };
                            @endphp
                            <x-ui.badge :variant="$statusVariant">
                                {{ $state->inGracePeriod ? __('Grace mode') : ucfirst($state->status) }}
                            </x-ui.badge>
                        </dd>
                    </div>

                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Source') }}</dt>
                        <dd class="font-medium">{{ $state->source }}</dd>
                    </div>

                    @if (filled($state->reason))
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Reason') }}</dt>
                            <dd class="font-medium">{{ $state->reason }}</dd>
                        </div>
                    @endif

                    @if (filled($state->message))
                        <div>
                            <dt class="mb-1 text-muted-foreground">{{ __('Message') }}</dt>
                            <dd class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-foreground">
                                {{ $state->message }}
                            </dd>
                        </div>
                    @endif

                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Instance ID') }}</dt>
                        <dd class="break-all font-mono text-small">{{ $instanceId ?: __('Not configured') }}</dd>
                    </div>

                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('License key') }}</dt>
                        <dd class="break-all font-mono text-small">{{ $maskedLicenseKey ?: __('Not configured') }}</dd>
                    </div>

                    @if ($activation)
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Product type') }}</dt>
                            <dd class="font-medium">{{ $activation->product_type ?: '—' }}</dd>
                        </div>

                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Expires at') }}</dt>
                            <dd class="font-medium">
                                {{ $activation->expires_at?->toDayDateTimeString() ?: __('Never') }}
                            </dd>
                        </div>

                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Last validated') }}</dt>
                            <dd class="font-medium">
                                {{ $activation->last_validated_at?->diffForHumans() ?: '—' }}
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($canManage)
                    <form method="POST" action="{{ route('admin.license.revalidate') }}" class="mt-5">
                        @csrf
                        <x-ui.button type="submit" variant="secondary">
                            {{ __('Revalidate now') }}
                        </x-ui.button>
                    </form>
                @endif
            </x-ui.card>

            @if ($canManage)
                <x-ui.card :title="__('Update license key')">
                    <p class="mb-4 text-body-sm text-muted-foreground">
                        {{ __('Enter a new license key to replace the current one. The instance ID stays unchanged.') }}
                    </p>

                    <form method="POST" action="{{ route('admin.license.update') }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <x-ui.input
                            name="license_key"
                            type="password"
                            :label="__('New license key')"
                            required
                            autocomplete="off"
                        />

                        <div class="flex justify-end pt-2">
                            <x-ui.button type="submit" variant="primary">
                                {{ __('Save and validate') }}
                            </x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @endif
        </div>

        <x-ui.card :title="__('Entitlements')">
            @if ($entitlements === [])
                <x-ui.empty
                    :title="__('No entitlements')"
                    :description="__('No modules or themes are granted by the current license.')"
                />
            @else
                <div class="overflow-x-auto rounded-lg border border-border">
                    <table class="min-w-full divide-y divide-border text-start text-body-sm">
                        <thead class="bg-muted/60 text-small font-medium uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">{{ __('Type') }}</th>
                                <th class="px-4 py-3">{{ __('SKU') }}</th>
                                <th class="px-4 py-3">{{ __('Name') }}</th>
                                <th class="px-4 py-3">{{ __('Granted at') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($entitlements as $entitlement)
                                <tr>
                                    <td class="px-4 py-3">
                                        <x-ui.badge variant="neutral">{{ $entitlement->productType ?: '—' }}</x-ui.badge>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-small">{{ $entitlement->productSku }}</td>
                                    <td class="px-4 py-3">{{ $entitlement->productName }}</td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ $entitlement->grantedAt ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap gap-3 text-body-sm text-muted-foreground">
                    <span>{{ __('Modules') }}: {{ count($modules) }}</span>
                    <span>{{ __('Themes') }}: {{ count($themes) }}</span>
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layout.admin>

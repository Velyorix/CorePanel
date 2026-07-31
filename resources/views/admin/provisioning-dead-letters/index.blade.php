<?php

/**
 * @var \Illuminate\Pagination\LengthAwarePaginator<\Core\Provisioning\Models\ProvisioningDeadLetter> $letters
 * @var array{q: string|null, status: \Core\Provisioning\Enums\ProvisioningDeadLetterStatus|null, sort: string, dir: string} $filters
 * @var list<\Core\Provisioning\Enums\ProvisioningDeadLetterStatus> $statuses
 */
?>

<x-layout.admin
    :title="__('Provisioning failures')"
    :page-heading="__('Provisioning failures')"
>
    <x-slot:subtitle>
        {{ __('Review definitive provisioning failures, rollback local state, and requeue services.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Services'), 'url' => route('admin.services.index')],
            ['label' => __('Provisioning failures')],
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

    <x-ui.table :paginator="$letters">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.provisioning-dead-letters.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input
                        name="q"
                        :label="__('Search')"
                        :value="$filters['q']"
                        :placeholder="__('Module, message, hostname, client…')"
                    />
                </div>

                <div class="min-w-40">
                    <x-ui.select name="status" :label="__('Status')">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status']?->value ?? null) === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                @if (filled(request('sort')))
                    <input type="hidden" name="sort" value="{{ request('sort') }}">
                @endif
                @if (filled(request('dir')))
                    <input type="hidden" name="dir" value="{{ request('dir') }}">
                @endif

                <x-ui.button type="submit" variant="secondary" size="sm">
                    {{ __('Apply') }}
                </x-ui.button>

                @if (filled($filters['q']) || $filters['status'] !== null)
                    <x-ui.button :href="route('admin.provisioning-dead-letters.index', ['status' => '', ...request()->only(['sort', 'dir'])])" variant="ghost" size="sm">
                        {{ __('Clear') }}
                    </x-ui.button>
                @endif
            </form>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <x-ui.table-heading sort="id">{{ __('ID') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Service') }}</th>
                <x-ui.table-heading sort="module">{{ __('Module') }}</x-ui.table-heading>
                <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                <x-ui.table-heading sort="attempts">{{ __('Attempts') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Error') }}</th>
                <x-ui.table-heading sort="failed_at">{{ __('Failed at') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>

        <x-slot:empty>
            <tr>
                <td colspan="8" class="p-4">
                    <x-ui.empty
                        :title="__('No provisioning failures found')"
                        :description="filled($filters['q']) || $filters['status'] !== null
                            ? __('Try adjusting your search or filters.')
                            : __('Failed provisioning jobs will appear here for admin review.')"
                    />
                </td>
            </tr>
        </x-slot:empty>

        @foreach ($letters as $letter)
            @php
                $variant = match ($letter->status) {
                    \Core\Provisioning\Enums\ProvisioningDeadLetterStatus::PendingReview => 'warning',
                    \Core\Provisioning\Enums\ProvisioningDeadLetterStatus::Requeued => 'primary',
                    \Core\Provisioning\Enums\ProvisioningDeadLetterStatus::Resolved => 'success',
                    default => 'neutral',
                };
            @endphp
            <tr class="border-t border-border">
                <td class="px-4 py-3 text-sm">#{{ $letter->id }}</td>
                <td class="px-4 py-3 text-sm">
                    @if ($letter->service)
                        <a href="{{ route('admin.services.show', $letter->service) }}" class="font-medium text-fg hover:underline">
                            {{ $letter->service->hostname ?: __('Service #'.$letter->service->id) }}
                        </a>
                        <div class="text-xs text-muted">
                            {{ $letter->service->client?->company_name }}
                        </div>
                    @else
                        <span class="text-muted">{{ __('Deleted service') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm">{{ $letter->module ?: '—' }}</td>
                <td class="px-4 py-3">
                    <x-ui.badge :variant="$variant">{{ $letter->status->label() }}</x-ui.badge>
                </td>
                <td class="px-4 py-3 text-sm">{{ $letter->attempts }}</td>
                <td class="max-w-xs truncate px-4 py-3 text-sm text-muted" title="{{ $letter->exception_message }}">
                    {{ $letter->exception_message ?: '—' }}
                </td>
                <td class="px-4 py-3 text-sm text-muted">
                    {{ $letter->failed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                </td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button :href="route('admin.provisioning-dead-letters.show', $letter)" variant="ghost" size="sm">
                        {{ __('Review') }}
                    </x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>

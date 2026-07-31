<?php

/**
 * @var \Illuminate\Pagination\LengthAwarePaginator<\Core\Sync\Models\SyncLog> $logs
 * @var array{q: string|null, subject_type: \Core\Sync\Enums\SyncLogSubject|null, outcome: \Core\Sync\Enums\SyncLogOutcome|null, module: string|null, sort: string, dir: string} $filters
 * @var list<\Core\Sync\Enums\SyncLogSubject> $subjectTypes
 * @var list<\Core\Sync\Enums\SyncLogOutcome> $outcomes
 */
?>

<x-layout.admin
    :title="__('Sync history')"
    :page-heading="__('Sync history')"
>
    <x-slot:subtitle>
        {{ __('Review provider sync runs, divergences, and manual sync actions.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Services'), 'url' => route('admin.services.index')],
            ['label' => __('Sync history')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        @can('manage', \Core\Services\Models\Service::class)
            <form method="POST" action="{{ route('admin.sync.run') }}">
                @csrf
                <x-ui.button type="submit" variant="primary" size="sm">
                    {{ __('Run sync now') }}
                </x-ui.button>
            </form>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    <x-ui.table :paginator="$logs">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.sync-logs.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input
                        name="q"
                        :label="__('Search')"
                        :value="$filters['q']"
                        :placeholder="__('Module, message, subject ID…')"
                    />
                </div>

                <div class="min-w-36">
                    <x-ui.select name="subject_type" :label="__('Subject')">
                        <option value="">{{ __('All subjects') }}</option>
                        @foreach ($subjectTypes as $subjectType)
                            <option value="{{ $subjectType->value }}" @selected(($filters['subject_type']?->value ?? null) === $subjectType->value)>
                                {{ $subjectType->label() }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="min-w-36">
                    <x-ui.select name="outcome" :label="__('Outcome')">
                        <option value="">{{ __('All outcomes') }}</option>
                        @foreach ($outcomes as $outcome)
                            <option value="{{ $outcome->value }}" @selected(($filters['outcome']?->value ?? null) === $outcome->value)>
                                {{ $outcome->label() }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="min-w-32">
                    <x-ui.input
                        name="module"
                        :label="__('Module')"
                        :value="$filters['module']"
                        :placeholder="__('stub')"
                    />
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

                @if (filled($filters['q']) || $filters['subject_type'] !== null || $filters['outcome'] !== null || filled($filters['module']))
                    <x-ui.button :href="route('admin.sync-logs.index', request()->only(['sort', 'dir']))" variant="ghost" size="sm">
                        {{ __('Clear') }}
                    </x-ui.button>
                @endif
            </form>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <x-ui.table-heading sort="id">{{ __('ID') }}</x-ui.table-heading>
                <x-ui.table-heading sort="created_at">{{ __('When') }}</x-ui.table-heading>
                <x-ui.table-heading sort="subject_type">{{ __('Subject') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Resource') }}</th>
                <x-ui.table-heading sort="module">{{ __('Module') }}</x-ui.table-heading>
                <x-ui.table-heading sort="outcome">{{ __('Outcome') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Message') }}</th>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>

        <x-slot:empty>
            <tr>
                <td colspan="8" class="p-4">
                    <x-ui.empty
                        :title="__('No sync logs found')"
                        :description="filled($filters['q']) || $filters['subject_type'] !== null || $filters['outcome'] !== null || filled($filters['module'])
                            ? __('Try adjusting your search or filters.')
                            : __('Scheduled sync jobs and manual sync actions will appear here.')"
                    />
                </td>
            </tr>
        </x-slot:empty>

        @foreach ($logs as $log)
            <tr class="border-t border-border">
                <td class="px-4 py-3 text-sm">#{{ $log->id }}</td>
                <td class="px-4 py-3 text-sm text-muted">
                    {{ $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}
                </td>
                <td class="px-4 py-3 text-sm">{{ $log->subject_type->label() }}</td>
                <td class="px-4 py-3 text-sm">
                    @if ($url = $log->subjectUrl())
                        <a href="{{ $url }}" class="font-medium text-fg hover:underline">
                            {{ $log->subjectLabel() }}
                        </a>
                    @else
                        {{ $log->subjectLabel() }}
                    @endif
                </td>
                <td class="px-4 py-3 text-sm">{{ $log->module ?: '—' }}</td>
                <td class="px-4 py-3">
                    <x-ui.badge :variant="$log->outcome->badgeVariant()">{{ $log->outcome->label() }}</x-ui.badge>
                </td>
                <td class="max-w-xs truncate px-4 py-3 text-sm text-muted" title="{{ $log->message }}">
                    {{ $log->message ?: '—' }}
                </td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button :href="route('admin.sync-logs.show', $log)" variant="ghost" size="sm">
                        {{ __('Details') }}
                    </x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>

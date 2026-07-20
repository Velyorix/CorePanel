<?php

/**
 * @var \Core\Provisioning\Models\ProvisioningDeadLetter $letter
 */

use Core\Provisioning\Enums\ProvisioningDeadLetterStatus;

$statusVariant = match ($letter->status) {
    ProvisioningDeadLetterStatus::PendingReview => 'warning',
    ProvisioningDeadLetterStatus::Requeued => 'primary',
    ProvisioningDeadLetterStatus::Resolved => 'success',
    default => 'neutral',
};

$service = $letter->service;
$heading = __('Provisioning failure #:id', ['id' => $letter->id]);
?>

<x-layout.admin
    :title="$heading"
    :page-heading="$heading"
>
    <x-slot:subtitle>
        {{ __('Inspect failure details and take review actions.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Services'), 'url' => route('admin.services.index')],
            ['label' => __('Provisioning failures'), 'url' => route('admin.provisioning-dead-letters.index')],
            ['label' => '#'.$letter->id],
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

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card :title="__('Failure details')">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted">{{ __('Status') }}</dt>
                        <dd class="mt-1"><x-ui.badge :variant="$statusVariant">{{ $letter->status->label() }}</x-ui.badge></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted">{{ __('Attempts') }}</dt>
                        <dd class="mt-1 text-sm">{{ $letter->attempts }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted">{{ __('Module') }}</dt>
                        <dd class="mt-1 text-sm">{{ $letter->module ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted">{{ __('Failed at') }}</dt>
                        <dd class="mt-1 text-sm">{{ $letter->failed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-muted">{{ __('Exception') }}</dt>
                        <dd class="mt-1 break-all text-sm">{{ $letter->exception_class ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-muted">{{ __('Message') }}</dt>
                        <dd class="mt-1 whitespace-pre-wrap text-sm">{{ $letter->exception_message ?: '—' }}</dd>
                    </div>
                    @if ($letter->resolution_notes)
                        <div class="sm:col-span-2">
                            <dt class="text-xs uppercase tracking-wide text-muted">{{ __('Resolution notes') }}</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-sm">{{ $letter->resolution_notes }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>

            @if ($service)
                <x-ui.card :title="__('Service')">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-muted">{{ __('Service') }}</dt>
                            <dd class="mt-1 text-sm">
                                <a href="{{ route('admin.services.show', $service) }}" class="font-medium hover:underline">
                                    {{ $service->hostname ?: __('Service #'.$service->id) }}
                                </a>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-muted">{{ __('Service status') }}</dt>
                            <dd class="mt-1 text-sm">{{ $service->status->label() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-muted">{{ __('External ID') }}</dt>
                            <dd class="mt-1 text-sm">{{ $service->external_id ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-muted">{{ __('Node') }}</dt>
                            <dd class="mt-1 text-sm">{{ $service->node?->hostname ?: ($service->node_id ? '#'.$service->node_id : '—') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-muted">{{ __('Client') }}</dt>
                            <dd class="mt-1 text-sm">{{ $service->client?->company_name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-muted">{{ __('Product') }}</dt>
                            <dd class="mt-1 text-sm">{{ $service->product?->name ?: '—' }}</dd>
                        </div>
                    </dl>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            @if ($letter->isOpen() && $service)
                @can('manage', $service)
                    <x-ui.card :title="__('Review actions')">
                        <form method="POST" action="{{ route('admin.provisioning-dead-letters.requeue', $letter) }}" class="space-y-4">
                            @csrf
                            <div>
                                <label for="requeue-notes" class="mb-1.5 block text-body-sm font-medium text-foreground">{{ __('Notes') }}</label>
                                <textarea
                                    id="requeue-notes"
                                    name="notes"
                                    rows="3"
                                    class="block w-full rounded-md border border-border bg-surface px-3 py-2 text-body-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                >{{ old('notes') }}</textarea>
                            </div>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="release_node" value="1" class="rounded border-border">
                                <span>{{ __('Release assigned node before requeue') }}</span>
                            </label>
                            <x-ui.button type="submit" variant="primary" size="sm" class="w-full">
                                {{ __('Requeue provisioning') }}
                            </x-ui.button>
                        </form>

                        <hr class="my-4 border-border">

                        <form method="POST" action="{{ route('admin.provisioning-dead-letters.rollback', $letter) }}" class="space-y-4">
                            @csrf
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="clear_node" value="1" checked class="rounded border-border">
                                <span>{{ __('Clear assigned node') }}</span>
                            </label>
                            <x-ui.button type="submit" variant="secondary" size="sm" class="w-full">
                                {{ __('Rollback local state') }}
                            </x-ui.button>
                        </form>

                        <hr class="my-4 border-border">

                        <form method="POST" action="{{ route('admin.provisioning-dead-letters.resolve', $letter) }}" class="mb-3 space-y-4">
                            @csrf
                            <div>
                                <label for="resolve-notes" class="mb-1.5 block text-body-sm font-medium text-foreground">{{ __('Notes') }}</label>
                                <textarea
                                    id="resolve-notes"
                                    name="notes"
                                    rows="2"
                                    class="block w-full rounded-md border border-border bg-surface px-3 py-2 text-body-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                ></textarea>
                            </div>
                            <x-ui.button type="submit" variant="secondary" size="sm" class="w-full">
                                {{ __('Mark resolved') }}
                            </x-ui.button>
                        </form>

                        <form
                            method="POST"
                            action="{{ route('admin.provisioning-dead-letters.discard', $letter) }}"
                            class="space-y-4"
                            onsubmit="return confirm(@js(__('Discard this provisioning failure?')))"
                        >
                            @csrf
                            <div>
                                <label for="discard-notes" class="mb-1.5 block text-body-sm font-medium text-foreground">{{ __('Notes') }}</label>
                                <textarea
                                    id="discard-notes"
                                    name="notes"
                                    rows="2"
                                    class="block w-full rounded-md border border-border bg-surface px-3 py-2 text-body-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                ></textarea>
                            </div>
                            <x-ui.button type="submit" variant="danger" size="sm" class="w-full">
                                {{ __('Discard') }}
                            </x-ui.button>
                        </form>
                    </x-ui.card>
                @endcan
            @else
                <x-ui.card :title="__('Review closed')">
                    <p class="text-sm text-muted">
                        {{ __('This failure is no longer pending review.') }}
                    </p>
                    @if ($letter->resolved_at)
                        <p class="mt-2 text-sm">
                            {{ __('Closed at :date', ['date' => $letter->resolved_at->timezone(config('app.timezone'))->format('Y-m-d H:i')]) }}
                            @if ($letter->resolver)
                                — {{ $letter->resolver->name }}
                            @endif
                        </p>
                    @endif
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layout.admin>

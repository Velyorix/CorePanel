<x-layout.admin
    :title="__('Edit client')"
    :page-heading="__('Edit client')"
>
    <x-slot:subtitle>
        {{ $client->company_name ?: __('Untitled client') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Clients'), 'url' => route('admin.clients.index')],
            ['label' => $client->company_name ?: __('Client #'.$client->id), 'url' => route('admin.clients.show', $client)],
            ['label' => __('Edit')],
        ]" />
    </x-slot:breadcrumbs>

    <x-ui.card :title="__('Client information')">
        @if ($errors->any())
            <div class="mb-4">
                <x-ui.alert variant="danger" :title="__('Unable to update client')">
                    <ul class="list-disc ps-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.clients.update', $client) }}" class="space-y-6">
            @csrf
            @method('PUT')

            @include('admin.clients._form')

            <div class="flex flex-wrap items-center justify-end gap-2">
                <x-ui.button :href="route('admin.clients.show', $client)" variant="secondary">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button type="submit" variant="primary">
                    {{ __('Save changes') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>

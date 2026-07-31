<x-layout.admin
    :title="__('Create client')"
    :page-heading="__('Create client')"
>
    <x-slot:subtitle>
        {{ __('Add a new client account.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Clients'), 'url' => route('admin.clients.index')],
            ['label' => __('Create')],
        ]" />
    </x-slot:breadcrumbs>

    <x-ui.card :title="__('Client information')">
        @if ($errors->any())
            <div class="mb-4">
                <x-ui.alert variant="danger" :title="__('Unable to create client')">
                    <ul class="list-disc ps-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.clients.store') }}" class="space-y-6">
            @csrf

            @include('admin.clients._form')

            <div class="flex flex-wrap items-center justify-end gap-2">
                <x-ui.button :href="route('admin.clients.index')" variant="secondary">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button type="submit" variant="primary">
                    {{ __('Create client') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>

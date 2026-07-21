<x-layout.admin
    :title="__('Create server')"
    :page-heading="__('Create server')"
>
    <x-slot:subtitle>
        {{ __('Register a provisioning server and bind it to a provider module.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Nodes'), 'url' => route('admin.nodes.index')],
            ['label' => __('Create')],
        ]" />
    </x-slot:breadcrumbs>

    <x-ui.card :title="__('Server details')">
        @if ($errors->any())
            <div class="mb-4">
                <x-ui.alert variant="danger" :title="__('Unable to create server')">
                    <ul class="list-disc ps-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.nodes.store') }}" class="space-y-6">
            @csrf

            @include('admin.nodes._form')

            <div class="flex flex-wrap items-center justify-end gap-2">
                <x-ui.button :href="route('admin.nodes.index')" variant="secondary">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button type="submit" variant="primary">
                    {{ __('Create server') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>

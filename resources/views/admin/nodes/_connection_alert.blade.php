@if (session('connection_status'))
    <div class="mb-4">
        <x-ui.alert variant="success">{{ session('connection_status') }}</x-ui.alert>
    </div>
@endif

@if ($errors->has('connection'))
    <div class="mb-4">
        <x-ui.alert variant="danger" :title="__('Connection test failed')">
            {{ $errors->first('connection') }}
        </x-ui.alert>
    </div>
@endif

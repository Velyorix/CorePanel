<x-mail::message>
# {{ __('Client invitation') }}

{{ __('Someone invited you to join their client account in :app.', ['app' => $appName]) }}

{{ __('Client: :client', ['client' => $clientName]) }}

{{ __('Role: :role', ['role' => $role]) }}

<x-mail::button :url="$url">
{{ __('Accept invitation') }}
</x-mail::button>

{{ __('This invitation will expire soon if not accepted.') }}

Thanks,<br>
{{ $appName }}
</x-mail::message>

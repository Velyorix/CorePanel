<x-mail::message>
# {{ __('Service provisioned') }}

{{ __('Your service has been provisioned successfully.') }}

{{ __('Service: :label', ['label' => $serviceLabel]) }}

@if (! empty($hostname))
{{ __('Hostname: :hostname', ['hostname' => $hostname]) }}
@endif

@if (! empty($externalId))
{{ __('External ID: :id', ['id' => $externalId]) }}
@endif

@if (! empty($url))
<x-mail::button :url="$url">
{{ __('View service') }}
</x-mail::button>
@endif

Thanks,<br>
{{ $appName }}
</x-mail::message>

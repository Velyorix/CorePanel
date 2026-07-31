<x-mail::message>
# {{ $heading }}

{{ $intro }}

{{ __('Service: :label', ['label' => $serviceLabel]) }}

{{ __('Status: :status', ['status' => $statusLabel]) }}

@if (! empty($url))
<x-mail::button :url="$url">
{{ __('View service') }}
</x-mail::button>
@endif

Thanks,<br>
{{ $appName }}
</x-mail::message>

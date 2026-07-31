<x-mail::message>
# {{ __('Service provisioning failed') }}

{{ __('We could not finish provisioning your service.') }}

{{ __('Service: :label', ['label' => $serviceLabel]) }}

@if (! empty($errorMessage))
<x-mail::panel>
{{ $errorMessage }}
</x-mail::panel>
@endif

@if (! empty($url))
<x-mail::button :url="$url">
{{ __('View service') }}
</x-mail::button>
@endif

{{ __('Our team has been notified and will investigate.') }}

Thanks,<br>
{{ $appName }}
</x-mail::message>

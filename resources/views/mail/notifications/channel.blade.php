<x-mail::message>
# {{ $title }}

{{ $message }}

@if (! empty($actionUrl))
<x-mail::button :url="$actionUrl">
{{ $actionLabel ?: __('View details') }}
</x-mail::button>
@endif

Thanks,<br>
{{ $appName }}
</x-mail::message>

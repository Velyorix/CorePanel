<x-mail::message>
# {{ __('New ticket reply') }}

{{ __('There is a new reply on support ticket :number.', ['number' => $number]) }}

{{ __('Subject: :subject', ['subject' => $subject]) }}

{{ __('From: :name', ['name' => $authorName]) }}

<x-mail::panel>
{{ $excerpt }}
</x-mail::panel>

<x-mail::button :url="$url">
{{ __('View ticket') }}
</x-mail::button>

Thanks,<br>
{{ $appName }}
</x-mail::message>

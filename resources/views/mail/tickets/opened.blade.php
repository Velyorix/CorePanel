<x-mail::message>
# {{ __('New support ticket') }}

{{ __('A new support ticket has been opened.') }}

{{ __('Ticket: :number', ['number' => $number]) }}

{{ __('Subject: :subject', ['subject' => $subject]) }}

{{ __('Client: :client', ['client' => $clientName]) }}

<x-mail::button :url="$url">
{{ __('View ticket') }}
</x-mail::button>

Thanks,<br>
{{ $appName }}
</x-mail::message>

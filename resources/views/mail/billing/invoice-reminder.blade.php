<x-mail::message>
# {{ $heading }}

@foreach ($lines as $line)
{{ $line }}

@endforeach

{{ $footer }}

Thanks,<br>
{{ $appName }}
</x-mail::message>

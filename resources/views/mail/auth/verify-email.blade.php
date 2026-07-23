<x-mail::message>
# {{ __('Verify your email address') }}

{{ __('Please click the button below to verify your email address.') }}

<x-mail::button :url="$url">
{{ __('Verify email address') }}
</x-mail::button>

{{ __('This verification link will expire in :count minutes.', ['count' => $expireMinutes]) }}

{{ __('If you did not create an account, no further action is required.') }}

Thanks,<br>
{{ $appName }}
</x-mail::message>

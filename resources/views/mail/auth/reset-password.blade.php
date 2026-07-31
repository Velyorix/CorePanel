<x-mail::message>
# {{ __('Reset your password') }}

{{ __('You are receiving this email because we received a password reset request for your account.') }}

<x-mail::button :url="$url">
{{ __('Reset password') }}
</x-mail::button>

{{ __('This password reset link will expire in :count minutes.', ['count' => $expireMinutes]) }}

{{ __('If you did not request a password reset, no further action is required.') }}

Thanks,<br>
{{ $appName }}
</x-mail::message>

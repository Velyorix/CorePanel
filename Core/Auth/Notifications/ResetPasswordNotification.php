<?php

namespace Core\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter]
        public string $token,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expireMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $appName = (string) config('corepanel.name', config('app.name'));

        return (new MailMessage)
            ->subject(__('Reset your :app password', ['app' => $appName]))
            ->markdown('mail.auth.reset-password', [
                'url' => $url,
                'expireMinutes' => $expireMinutes,
                'appName' => $appName,
            ]);
    }
}

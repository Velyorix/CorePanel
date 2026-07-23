<?php

namespace Core\Auth\Notifications;

use Core\Auth\Services\EmailVerificationGate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $gate = app(EmailVerificationGate::class);
        $expireMinutes = $gate->expireMinutes();
        $appName = (string) config('corepanel.name', config('app.name'));

        $url = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes($expireMinutes),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        return (new MailMessage)
            ->subject(__('Verify your :app email address', ['app' => $appName]))
            ->markdown('mail.auth.verify-email', [
                'url' => $url,
                'expireMinutes' => $expireMinutes,
                'appName' => $appName,
            ]);
    }
}

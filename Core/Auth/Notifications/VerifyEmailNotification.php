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

        $url = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes($gate->expireMinutes()),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        return (new MailMessage)
            ->subject(__('Verify your :app email address', ['app' => config('corepanel.name')]))
            ->line(__('Please click the button below to verify your email address.'))
            ->action(__('Verify email address'), $url)
            ->line(__('This verification link will expire in :count minutes.', [
                'count' => $gate->expireMinutes(),
            ]))
            ->line(__('If you did not create an account, no further action is required.'));
    }
}

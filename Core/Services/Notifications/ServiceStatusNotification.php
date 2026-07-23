<?php

namespace Core\Services\Notifications;

use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail template for service lifecycle status changes.
 */
class ServiceStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Service $service,
        public readonly ServiceStatus $status,
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
        $this->service->loadMissing('product');

        $appName = (string) config('corepanel.name', config('app.name'));
        $serviceLabel = $this->serviceLabel();
        [$heading, $intro] = $this->copyForStatus();

        return (new MailMessage)
            ->subject(__(':heading — :service', [
                'heading' => $heading,
                'service' => $serviceLabel,
            ]))
            ->markdown('mail.services.status-changed', [
                'heading' => $heading,
                'intro' => $intro,
                'serviceLabel' => $serviceLabel,
                'statusLabel' => $this->status->label(),
                'url' => $this->serviceUrl(),
                'appName' => $appName,
            ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function copyForStatus(): array
    {
        return match ($this->status) {
            ServiceStatus::Active => [
                __('Service activated'),
                __('Your service is now active and ready to use.'),
            ],
            ServiceStatus::Suspended => [
                __('Service suspended'),
                __('Your service has been suspended.'),
            ],
            ServiceStatus::Terminated => [
                __('Service terminated'),
                __('Your service has been terminated.'),
            ],
            ServiceStatus::Cancelled => [
                __('Service cancelled'),
                __('Your service order has been cancelled.'),
            ],
            default => [
                __('Service status updated'),
                __('The status of your service has changed.'),
            ],
        };
    }

    private function serviceLabel(): string
    {
        if (filled($this->service->hostname)) {
            return (string) $this->service->hostname;
        }

        if (filled($this->service->product?->name)) {
            return (string) $this->service->product->name;
        }

        return __('Service #:id', ['id' => $this->service->id]);
    }

    private function serviceUrl(): ?string
    {
        if (! app('router')->has('client.services.show')) {
            return null;
        }

        return url(route('client.services.show', $this->service));
    }
}

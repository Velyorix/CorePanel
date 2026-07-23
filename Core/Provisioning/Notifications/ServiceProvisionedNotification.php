<?php

namespace Core\Provisioning\Notifications;

use Core\Services\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail template when provisioning completes successfully.
 */
class ServiceProvisionedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Service $service,
        public readonly ?string $externalId = null,
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
        $externalId = $this->externalId ?? $this->service->external_id;

        return (new MailMessage)
            ->subject(__('Service provisioned: :service', ['service' => $serviceLabel]))
            ->markdown('mail.provisioning.succeeded', [
                'serviceLabel' => $serviceLabel,
                'hostname' => $this->service->hostname,
                'externalId' => $externalId,
                'url' => $this->serviceUrl(),
                'appName' => $appName,
            ]);
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

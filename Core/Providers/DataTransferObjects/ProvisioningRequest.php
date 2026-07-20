<?php

namespace Core\Providers\DataTransferObjects;

use Core\Products\Enums\BillingCycle;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use InvalidArgumentException;

final readonly class ProvisioningRequest
{
    /**
     * @param  array<string, mixed>|null  $configData
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $serviceId,
        public int $clientId,
        public int $productId,
        public string $module,
        public ServiceStatus $status,
        public ?int $orderId = null,
        public ?int $orderItemId = null,
        public ?BillingCycle $billingCycle = null,
        public ?int $customIntervalDays = null,
        public ?string $hostname = null,
        public ?string $externalId = null,
        public ?string $ipAddress = null,
        public ?int $nodeId = null,
        public ?array $configData = null,
        public ?NodeConnectionRequest $node = null,
        public array $metadata = [],
    ) {
        if ($this->module === '') {
            throw new InvalidArgumentException('Provisioning module key is required.');
        }
    }

    public static function fromService(Service $service, ?NodeConnectionRequest $node = null): self
    {
        $module = trim((string) ($service->module ?? ''));

        if ($module === '') {
            throw new InvalidArgumentException('Service module is required to build a provisioning request.');
        }

        return new self(
            serviceId: (int) $service->id,
            clientId: (int) $service->client_id,
            productId: (int) $service->product_id,
            module: $module,
            status: $service->status,
            orderId: $service->order_id !== null ? (int) $service->order_id : null,
            orderItemId: $service->order_item_id !== null ? (int) $service->order_item_id : null,
            billingCycle: $service->billing_cycle,
            customIntervalDays: $service->custom_interval_days,
            hostname: $service->hostname,
            externalId: $service->external_id,
            ipAddress: $service->ip_address,
            nodeId: $service->node_id,
            configData: $service->config_data,
            node: $node,
        );
    }
}

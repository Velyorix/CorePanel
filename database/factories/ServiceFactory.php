<?php

namespace Database\Factories;

use Core\Clients\Models\Client;
use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'product_id' => Product::factory(),
            'order_id' => null,
            'order_item_id' => null,
            'status' => ServiceStatus::Pending,
            'module' => null,
            'external_id' => null,
            'billing_cycle' => BillingCycle::Monthly,
            'custom_interval_days' => null,
            'config_data' => null,
            'ip_address' => null,
            'hostname' => null,
            'node_id' => null,
            'started_at' => null,
            'ended_at' => null,
            'renewal_date' => null,
            'next_billing_date' => null,
            'provisioned_at' => null,
            'suspended_at' => null,
            'terminated_at' => null,
        ];
    }

    public function provisioning(): static
    {
        return $this->state(fn (): array => [
            'status' => ServiceStatus::Provisioning,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => ServiceStatus::Active,
            'provisioned_at' => now()->subHour(),
            'started_at' => now()->subHour(),
            'next_billing_date' => now()->addMonth(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => ServiceStatus::Suspended,
            'provisioned_at' => now()->subDays(10),
            'started_at' => now()->subDays(10),
            'suspended_at' => now()->subDay(),
            'next_billing_date' => now()->addMonth(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => ServiceStatus::Failed,
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn (): array => [
            'status' => ServiceStatus::Terminated,
            'provisioned_at' => now()->subMonths(2),
            'started_at' => now()->subMonths(2),
            'terminated_at' => now()->subDay(),
            'ended_at' => now()->subDay(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => ServiceStatus::Cancelled,
            'ended_at' => now(),
        ]);
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (): array => [
            'client_id' => $client->id,
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (): array => [
            'product_id' => $product->id,
            'module' => $product->module,
        ]);
    }
}

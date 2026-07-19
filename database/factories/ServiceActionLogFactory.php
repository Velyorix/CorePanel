<?php

namespace Database\Factories;

use Core\Auth\Models\User;
use Core\Services\Enums\ServiceAction;
use Core\Services\Enums\ServiceActionLogStatus;
use Core\Services\Models\Service;
use Core\Services\Models\ServiceActionLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceActionLog>
 */
class ServiceActionLogFactory extends Factory
{
    protected $model = ServiceActionLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'action' => ServiceAction::Restart,
            'status' => ServiceActionLogStatus::Success,
            'response' => null,
            'performed_by' => null,
            'created_at' => now(),
        ];
    }

    public function forService(Service $service): static
    {
        return $this->state(fn (): array => [
            'service_id' => $service->id,
        ]);
    }

    public function performedBy(User $user): static
    {
        return $this->state(fn (): array => [
            'performed_by' => $user->id,
        ]);
    }

    public function success(): static
    {
        return $this->state(fn (): array => [
            'status' => ServiceActionLogStatus::Success,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => ServiceActionLogStatus::Failed,
            'response' => ['reason' => 'Module action failed.'],
        ]);
    }

    public function skipped(): static
    {
        return $this->state(fn (): array => [
            'status' => ServiceActionLogStatus::Skipped,
        ]);
    }

    public function action(ServiceAction $action): static
    {
        return $this->state(fn (): array => [
            'action' => $action,
        ]);
    }
}

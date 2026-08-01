<?php

namespace Core\Services\Services;

use Core\Services\Enums\ServiceStatus;
use Core\Services\Events\ServiceSuspended;
use Core\Services\Events\ServiceTerminated;
use Core\Services\Models\Service;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServiceLifecycleService
{
    public function markProvisioning(Service $service): Service
    {
        if ($service->status === ServiceStatus::Provisioning) {
            return $service->fresh(['client', 'product']) ?? $service;
        }

        if (! $service->status->canTransitionTo(ServiceStatus::Provisioning)) {
            throw new RuntimeException('Only pending or failed services can enter provisioning.');
        }

        return $this->transition($service, ServiceStatus::Provisioning, [
            'ended_at' => null,
            'terminated_at' => null,
            'suspended_at' => null,
        ]);
    }

    public function markActive(Service $service): Service
    {
        if ($service->status === ServiceStatus::Active) {
            return $service->fresh(['client', 'product']) ?? $service;
        }

        if (! $service->status->canTransitionTo(ServiceStatus::Active)) {
            throw new RuntimeException('Only provisioning or suspended services can become active.');
        }

        $now = now();

        return $this->transition($service, ServiceStatus::Active, [
            'provisioned_at' => $service->provisioned_at ?? $now,
            'started_at' => $service->started_at ?? $now,
            'suspended_at' => null,
            'ended_at' => null,
            'terminated_at' => null,
        ]);
    }

    public function markFailed(Service $service): Service
    {
        if ($service->status === ServiceStatus::Failed) {
            return $service->fresh(['client', 'product']) ?? $service;
        }

        if (! $service->status->canTransitionTo(ServiceStatus::Failed)) {
            throw new RuntimeException('Only provisioning services can be marked failed.');
        }

        return $this->transition($service, ServiceStatus::Failed, [
            'suspended_at' => null,
        ]);
    }

    public function suspend(Service $service): Service
    {
        if ($service->status === ServiceStatus::Suspended) {
            return $service->fresh(['client', 'product']) ?? $service;
        }

        if (! $service->status->canTransitionTo(ServiceStatus::Suspended)) {
            throw new RuntimeException('Only active services can be suspended.');
        }

        return $this->transition($service, ServiceStatus::Suspended, [
            'suspended_at' => $service->suspended_at ?? now(),
        ]);
    }

    public function unsuspend(Service $service): Service
    {
        if ($service->status === ServiceStatus::Active) {
            return $service->fresh(['client', 'product']) ?? $service;
        }

        if ($service->status !== ServiceStatus::Suspended) {
            throw new RuntimeException('Only suspended services can be unsuspended.');
        }

        return $this->markActive($service);
    }

    public function terminate(Service $service): Service
    {
        if ($service->status === ServiceStatus::Terminated) {
            return $service->fresh(['client', 'product']) ?? $service;
        }

        if (! $service->status->canTransitionTo(ServiceStatus::Terminated)) {
            throw new RuntimeException('Only active or suspended services can be terminated.');
        }

        $now = now();

        return $this->transition($service, ServiceStatus::Terminated, [
            'terminated_at' => $service->terminated_at ?? $now,
            'ended_at' => $service->ended_at ?? $now,
            'suspended_at' => null,
        ]);
    }

    public function cancel(Service $service): Service
    {
        if ($service->status === ServiceStatus::Cancelled) {
            return $service->fresh(['client', 'product']) ?? $service;
        }

        if (! $service->status->canTransitionTo(ServiceStatus::Cancelled)) {
            throw new RuntimeException('Only pending, provisioning, or failed services can be cancelled.');
        }

        return $this->transition($service, ServiceStatus::Cancelled, [
            'ended_at' => $service->ended_at ?? now(),
            'suspended_at' => null,
            'terminated_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function transition(Service $service, ServiceStatus $target, array $extra = []): Service
    {
        if (! $service->status->canTransitionTo($target)) {
            throw new RuntimeException(sprintf(
                'Cannot transition service from %s to %s.',
                $service->status->value,
                $target->value,
            ));
        }

        return DB::transaction(function () use ($service, $target, $extra): Service {
            $service->forceFill([
                'status' => $target,
                ...$extra,
            ])->save();

            $fresh = $service->fresh(['client', 'product']) ?? $service;

            match ($target) {
                ServiceStatus::Suspended => event(new ServiceSuspended($fresh)),
                ServiceStatus::Terminated => event(new ServiceTerminated($fresh)),
                default => null,
            };

            return $fresh;
        });
    }
}

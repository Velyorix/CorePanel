<?php

namespace Core\Services\Services;

use Core\Auth\Models\User;
use Core\Services\Contracts\ModuleActionDispatcher;
use Core\Services\Contracts\ServiceActionLogger;
use Core\Services\Enums\ServiceAction;
use Core\Services\Enums\ServiceActionLogStatus;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServiceControlService
{
    public function __construct(
        private readonly ServiceLifecycleService $lifecycle,
        private readonly ModuleActionDispatcher $modules,
        private readonly ServiceActionLogger $logger,
    ) {
    }

    public function start(Service $service, ?User $performedBy = null): Service
    {
        return $this->execute($service, ServiceAction::Start, $performedBy);
    }

    public function stop(Service $service, ?User $performedBy = null): Service
    {
        return $this->execute($service, ServiceAction::Stop, $performedBy);
    }

    public function restart(Service $service, ?User $performedBy = null): Service
    {
        return $this->execute($service, ServiceAction::Restart, $performedBy);
    }

    public function suspend(Service $service, ?User $performedBy = null): Service
    {
        return $this->execute($service, ServiceAction::Suspend, $performedBy);
    }

    public function unsuspend(Service $service, ?User $performedBy = null): Service
    {
        return $this->execute($service, ServiceAction::Unsuspend, $performedBy);
    }

    public function terminate(Service $service, ?User $performedBy = null): Service
    {
        return $this->execute($service, ServiceAction::Terminate, $performedBy);
    }

    public function reinstall(Service $service, ?User $performedBy = null): Service
    {
        return $this->execute($service, ServiceAction::Reinstall, $performedBy);
    }

    public function execute(Service $service, ServiceAction $action, ?User $performedBy = null): Service
    {
        $service = $service->fresh(['client', 'product']) ?? $service;

        if ($this->isAlreadySatisfied($service, $action)) {
            $this->logger->record(
                $service,
                $action,
                ServiceActionLogStatus::Skipped,
                $performedBy?->id,
                ['reason' => 'Service already in the target state.'],
            );

            return $service;
        }

        if (! $action->isAllowedFor($service->status)) {
            throw new RuntimeException(sprintf(
                'Action [%s] is not allowed for service status [%s].',
                $action->value,
                $service->status->value,
            ));
        }

        $moduleResult = $this->modules->dispatch($service, $action);
        $moduleStatus = (string) ($moduleResult['status'] ?? 'failed');
        /** @var array<string, mixed> $moduleResponse */
        $moduleResponse = is_array($moduleResult['response'] ?? null)
            ? $moduleResult['response']
            : [];

        if ($moduleStatus === 'failed') {
            // Persist outside any transaction so the failure trail is not rolled back.
            $this->logger->record(
                $service,
                $action,
                ServiceActionLogStatus::Failed,
                $performedBy?->id,
                $moduleResponse,
            );

            throw new RuntimeException(sprintf(
                'Module action [%s] failed for service #%d.',
                $action->value,
                $service->id,
            ));
        }

        return DB::transaction(function () use ($service, $action, $performedBy, $moduleStatus, $moduleResponse): Service {
            $service = $this->applyLifecycle($service, $action);

            $logStatus = $moduleStatus === 'skipped'
                ? ServiceActionLogStatus::Skipped
                : ServiceActionLogStatus::Success;

            $this->logger->record(
                $service,
                $action,
                $logStatus,
                $performedBy?->id,
                $moduleResponse,
            );

            return $service->fresh(['client', 'product']) ?? $service;
        });
    }

    private function isAlreadySatisfied(Service $service, ServiceAction $action): bool
    {
        return match ($action) {
            ServiceAction::Suspend => $service->status === ServiceStatus::Suspended,
            ServiceAction::Unsuspend => $service->status === ServiceStatus::Active,
            ServiceAction::Terminate => $service->status === ServiceStatus::Terminated,
            default => false,
        };
    }

    private function applyLifecycle(Service $service, ServiceAction $action): Service
    {
        return match ($action) {
            ServiceAction::Suspend => $this->lifecycle->suspend($service),
            ServiceAction::Unsuspend => $this->lifecycle->unsuspend($service),
            ServiceAction::Terminate => $this->lifecycle->terminate($service),
            default => $service,
        };
    }
}

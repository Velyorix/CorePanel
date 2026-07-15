<?php

namespace Core\Permissions\Services;

use Core\Auth\Models\User;
use Illuminate\Support\Facades\Gate;

class GateRegistrar
{
    public function __construct(
        private readonly PermissionRegistry $permissionRegistry,
        private readonly PermissionService $permissionService,
    ) {
    }

    public function register(): void
    {
        Gate::before(function (?User $user, string $ability, mixed ...$arguments): ?bool {
            if ($user === null || ! $this->permissionRegistry->contains($ability)) {
                return null;
            }

            $subject = $arguments[0] ?? null;

            return $this->permissionService->userCan($user, $ability, $subject);
        });
    }
}

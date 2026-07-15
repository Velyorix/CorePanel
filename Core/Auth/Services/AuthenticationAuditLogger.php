<?php

namespace Core\Auth\Services;

use Core\Auth\Models\User;
use Core\Support\Services\AuditLogger;

class AuthenticationAuditLogger
{
    public const ACTION_LOGIN_SUCCESS = 'auth.login.success';

    public const ACTION_LOGIN_FAILED = 'auth.login.failed';

    public const ACTION_LOGOUT = 'auth.logout';

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function logLoginSuccess(User $user, string $ipAddress, ?string $userAgent): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->auditLogger->record(
            action: self::ACTION_LOGIN_SUCCESS,
            actorId: $user->id,
            entityType: User::class,
            entityId: $user->id,
            after: [
                'email' => $user->email,
            ],
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );
    }

    public function logLoginFailed(
        string $email,
        string $reason,
        string $ipAddress,
        ?string $userAgent,
        ?User $user = null,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $context = [
            'email' => $email,
            'reason' => $reason,
        ];

        if ($user !== null) {
            $context['failed_login_attempts'] = $user->failed_login_attempts;
            $context['locked'] = $user->status === 'locked';
        }

        $this->auditLogger->record(
            action: self::ACTION_LOGIN_FAILED,
            actorId: $user?->id,
            entityType: $user !== null ? User::class : null,
            entityId: $user?->id,
            after: $context,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );
    }

    public function logLogout(User $user, string $ipAddress, ?string $userAgent): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->auditLogger->record(
            action: self::ACTION_LOGOUT,
            actorId: $user->id,
            entityType: User::class,
            entityId: $user->id,
            after: [
                'email' => $user->email,
            ],
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );
    }

    public function isEnabled(): bool
    {
        return (bool) config('corepanel.auth.audit.enabled', true);
    }
}

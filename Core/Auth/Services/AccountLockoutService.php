<?php

namespace Core\Auth\Services;

use Core\Auth\Models\User;

class AccountLockoutService
{
    public function isEnabled(): bool
    {
        return (bool) config('corepanel.auth.lockout.enabled', true);
    }

    public function isLocked(User $user): bool
    {
        if (! $this->isEnabled() || $user->status !== 'locked') {
            return false;
        }

        if ($this->lockoutMinutes() === null) {
            return true;
        }

        if ($user->locked_at === null) {
            return true;
        }

        if ($user->locked_at->copy()->addMinutes($this->lockoutMinutes())->isPast()) {
            $this->unlock($user);

            return false;
        }

        return true;
    }

    public function recordFailedAttempt(User $user): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $attempts = $user->failed_login_attempts + 1;

        $user->forceFill([
            'failed_login_attempts' => $attempts,
        ])->save();

        if ($attempts >= $this->maxAttempts()) {
            $this->lock($user);
        }
    }

    public function clearFailedAttempts(User $user): void
    {
        if ($user->failed_login_attempts === 0 && $user->status !== 'locked') {
            return;
        }

        $this->unlock($user);
    }

    public function lock(User $user): void
    {
        $user->forceFill([
            'status' => 'locked',
            'locked_at' => now(),
        ])->save();
    }

    public function unlock(User $user): void
    {
        $user->forceFill([
            'status' => 'active',
            'failed_login_attempts' => 0,
            'locked_at' => null,
        ])->save();
    }

    public function maxAttempts(): int
    {
        return (int) config('corepanel.auth.lockout.max_attempts', 10);
    }

    public function lockoutMinutes(): ?int
    {
        $minutes = config('corepanel.auth.lockout.lockout_minutes');

        if ($minutes === null) {
            return null;
        }

        return (int) $minutes;
    }
}

<?php

namespace Core\Tickets\Services;

use Core\Auth\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Anti-spam limits for client ticket create and reply actions.
 */
class TicketRateLimiter
{
    public function createKey(User $user): string
    {
        return 'ticket-create|'.$user->id;
    }

    public function replyKey(User $user): string
    {
        return 'ticket-reply|'.$user->id;
    }

    public function tooManyCreateAttempts(User $user): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->createKey($user),
            $this->createMaxAttempts(),
        );
    }

    public function hitCreate(User $user): void
    {
        RateLimiter::hit(
            $this->createKey($user),
            $this->createDecaySeconds(),
        );
    }

    public function clearCreate(User $user): void
    {
        RateLimiter::clear($this->createKey($user));
    }

    public function availableInCreate(User $user): int
    {
        return RateLimiter::availableIn($this->createKey($user));
    }

    public function tooManyReplyAttempts(User $user): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->replyKey($user),
            $this->replyMaxAttempts(),
        );
    }

    public function hitReply(User $user): void
    {
        RateLimiter::hit(
            $this->replyKey($user),
            $this->replyDecaySeconds(),
        );
    }

    public function clearReply(User $user): void
    {
        RateLimiter::clear($this->replyKey($user));
    }

    public function availableInReply(User $user): int
    {
        return RateLimiter::availableIn($this->replyKey($user));
    }

    public function createMaxAttempts(): int
    {
        return max(1, (int) config('corepanel.tickets.rate_limit.create.max_attempts', 5));
    }

    public function createDecaySeconds(): int
    {
        return max(1, (int) config('corepanel.tickets.rate_limit.create.decay_seconds', 3600));
    }

    public function replyMaxAttempts(): int
    {
        return max(1, (int) config('corepanel.tickets.rate_limit.reply.max_attempts', 20));
    }

    public function replyDecaySeconds(): int
    {
        return max(1, (int) config('corepanel.tickets.rate_limit.reply.decay_seconds', 3600));
    }
}

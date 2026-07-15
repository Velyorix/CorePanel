<?php

namespace Core\Auth\Services;

use Core\Auth\Models\User;
use Core\Auth\Models\UserSession;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Session;

class UserSessionManager
{
    /**
     * @return Collection<int, UserSession>
     */
    public function activeSessionsFor(User $user): Collection
    {
        return UserSession::query()
            ->where('user_id', $user->id)
            ->active()
            ->orderByDesc('last_activity_at')
            ->get();
    }

    public function revoke(UserSession $userSession): void
    {
        if ($userSession->session_id !== null && $userSession->session_id !== '') {
            $this->destroyDriverSession($userSession->session_id);
        }

        $userSession->delete();
    }

    public function revokeAllExcept(User $user, string $exceptSessionId): int
    {
        $sessions = UserSession::query()
            ->where('user_id', $user->id)
            ->where('session_id', '!=', $exceptSessionId)
            ->get();

        foreach ($sessions as $session) {
            $this->revoke($session);
        }

        return $sessions->count();
    }

    public function revokeAllForUser(User $user): int
    {
        $sessions = UserSession::query()
            ->where('user_id', $user->id)
            ->get();

        foreach ($sessions as $session) {
            $this->revoke($session);
        }

        return $sessions->count();
    }

    public function isCurrent(UserSession $userSession, string $currentSessionId): bool
    {
        return $userSession->session_id === $currentSessionId;
    }

    public function destroyDriverSession(string $sessionId): void
    {
        Session::driver()->getHandler()->destroy($sessionId);
    }
}

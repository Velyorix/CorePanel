<?php

namespace Core\Auth\Services;

use Core\Auth\Models\User;
use Core\Auth\Models\UserSession;
use Core\Auth\Support\UserAgentParser;
use Illuminate\Support\Carbon;

class UserSessionTracker
{
    public function __construct(
        private readonly UserSessionManager $userSessionManager,
    ) {
    }
    public function record(User $user, string $sessionId, string $ipAddress, ?string $userAgent): UserSession
    {
        $now = now();

        $userSession = UserSession::query()->firstOrNew([
            'session_id' => $sessionId,
        ]);

        if (! $userSession->exists) {
            $userSession->created_at = $now;
        }

        $userSession->fill([
            'user_id' => $user->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_name' => UserAgentParser::deviceName($userAgent),
            'last_activity_at' => $now,
            'expires_at' => $this->expiresAt($now),
        ])->save();

        return $userSession;
    }

    public function sync(User $user, string $sessionId, string $ipAddress, ?string $userAgent): void
    {
        if (! config('corepanel.auth.session.track_activity', true)) {
            return;
        }

        $userSession = UserSession::query()
            ->where('session_id', $sessionId)
            ->first();

        if ($userSession === null) {
            $userSession = UserSession::query()
                ->where('user_id', $user->id)
                ->where('ip_address', $ipAddress)
                ->when($userAgent !== null, fn ($query) => $query->where('user_agent', $userAgent))
                ->orderByDesc('last_activity_at')
                ->first();
        }

        if ($userSession === null) {
            $this->record($user, $sessionId, $ipAddress, $userAgent);

            return;
        }

        $interval = (int) config('corepanel.auth.session.activity_touch_interval_seconds', 60);
        $now = now();

        if (
            $userSession->session_id !== $sessionId
            || $userSession->last_activity_at === null
            || $userSession->last_activity_at->lte($now->copy()->subSeconds($interval))
        ) {
            $userSession->fill([
                'session_id' => $sessionId,
                'last_activity_at' => $now,
                'expires_at' => $this->expiresAt($now),
            ])->save();
        }
    }

    public function revoke(
        string $sessionId,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $userSession = UserSession::query()
            ->where('session_id', $sessionId)
            ->first();

        if ($userSession !== null) {
            $this->userSessionManager->revoke($userSession);

            return;
        }

        if ($userId === null) {
            $this->userSessionManager->destroyDriverSession($sessionId);

            return;
        }

        $fallbackSession = UserSession::query()
            ->where('user_id', $userId)
            ->where('ip_address', $ipAddress)
            ->when($userAgent !== null, fn ($query) => $query->where('user_agent', $userAgent))
            ->orderByDesc('last_activity_at')
            ->first();

        if ($fallbackSession !== null) {
            $this->userSessionManager->revoke($fallbackSession);

            return;
        }

        $this->userSessionManager->destroyDriverSession($sessionId);
    }

    private function expiresAt(Carbon $from): Carbon
    {
        return $from->copy()->addMinutes((int) config('session.lifetime', 120));
    }
}

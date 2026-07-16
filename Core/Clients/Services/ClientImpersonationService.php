<?php

namespace Core\Clients\Services;

use Core\Auth\Models\User;
use Core\Auth\Services\UserSessionTracker;
use Core\Clients\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ClientImpersonationService
{
    public const SESSION_IMPERSONATOR_ID = 'impersonator_id';

    public const SESSION_IMPERSONATED_CLIENT_ID = 'impersonated_client_id';

    public function __construct(
        private readonly ClientAuditLogger $clientAuditLogger,
        private readonly UserSessionTracker $userSessionTracker,
    ) {
    }

    public function isImpersonating(?Request $request = null): bool
    {
        $request ??= request();

        return $request->hasSession()
            && filled($request->session()->get(self::SESSION_IMPERSONATOR_ID));
    }

    public function impersonatorId(?Request $request = null): ?int
    {
        $request ??= request();

        if (! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get(self::SESSION_IMPERSONATOR_ID);

        return $id !== null ? (int) $id : null;
    }

    public function impersonatedClientId(?Request $request = null): ?int
    {
        $request ??= request();

        if (! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get(self::SESSION_IMPERSONATED_CLIENT_ID);

        return $id !== null ? (int) $id : null;
    }

    public function start(User $actor, Client $client, Request $request): User
    {
        if ($this->isImpersonating($request)) {
            throw new RuntimeException('Already impersonating a user. Leave the current session first.');
        }

        if ($client->user_id === null) {
            throw new RuntimeException('This client has no primary owner to impersonate.');
        }

        /** @var User|null $target */
        $target = User::query()->find($client->user_id);

        if ($target === null) {
            throw new RuntimeException('The client owner account no longer exists.');
        }

        if ($target->id === $actor->id) {
            throw new RuntimeException('You cannot impersonate your own account.');
        }

        if ($target->status !== 'active') {
            throw new RuntimeException('The client owner account is not active.');
        }

        $request->session()->put(self::SESSION_IMPERSONATOR_ID, $actor->id);
        $request->session()->put(self::SESSION_IMPERSONATED_CLIENT_ID, $client->id);

        Auth::loginUsingId($target->id);
        $request->session()->regenerate();

        /** @var User $authenticatedTarget */
        $authenticatedTarget = Auth::user();

        $this->userSessionTracker->record(
            $authenticatedTarget,
            $request->session()->getId(),
            (string) $request->ip(),
            $request->userAgent(),
        );

        $this->clientAuditLogger->log(
            ClientAuditLogger::ACTION_IMPERSONATION_STARTED,
            $client,
            after: [
                'impersonator_id' => $actor->id,
                'impersonator_email' => $actor->email,
                'impersonated_user_id' => $authenticatedTarget->id,
                'impersonated_email' => $authenticatedTarget->email,
            ],
            actorId: $actor->id,
        );

        return $authenticatedTarget;
    }

    public function leave(Request $request): User
    {
        if (! $this->isImpersonating($request)) {
            throw new RuntimeException('No active impersonation session.');
        }

        $impersonatorId = $this->impersonatorId($request);
        $clientId = $this->impersonatedClientId($request);
        $impersonated = $request->user();

        /** @var User|null $impersonator */
        $impersonator = User::query()->find($impersonatorId);

        if ($impersonator === null) {
            $request->session()->forget([
                self::SESSION_IMPERSONATOR_ID,
                self::SESSION_IMPERSONATED_CLIENT_ID,
            ]);

            throw new RuntimeException('The original administrator account no longer exists.');
        }

        $client = $clientId !== null
            ? Client::query()->find($clientId)
            : null;

        $request->session()->forget([
            self::SESSION_IMPERSONATOR_ID,
            self::SESSION_IMPERSONATED_CLIENT_ID,
        ]);

        Auth::loginUsingId($impersonator->id);
        $request->session()->regenerate();

        /** @var User $authenticatedImpersonator */
        $authenticatedImpersonator = Auth::user();

        $this->userSessionTracker->record(
            $authenticatedImpersonator,
            $request->session()->getId(),
            (string) $request->ip(),
            $request->userAgent(),
        );

        if ($client !== null) {
            $this->clientAuditLogger->log(
                ClientAuditLogger::ACTION_IMPERSONATION_STOPPED,
                $client,
                after: [
                    'impersonator_id' => $authenticatedImpersonator->id,
                    'impersonator_email' => $authenticatedImpersonator->email,
                    'impersonated_user_id' => $impersonated?->id,
                    'impersonated_email' => $impersonated?->email,
                ],
                actorId: $authenticatedImpersonator->id,
            );
        }

        return $authenticatedImpersonator;
    }
}

<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Core\Auth\Models\UserSession;
use Core\Auth\Services\UserSessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserSessionController extends Controller
{
    public function __construct(
        private readonly UserSessionManager $userSessionManager,
    ) {
    }

    public function index(Request $request): View
    {
        $currentSessionId = $request->session()->getId();
        $sessions = $this->userSessionManager->activeSessionsFor($request->user());

        return view('account.sessions', [
            'sessions' => $sessions,
            'currentSessionId' => $currentSessionId,
        ]);
    }

    public function destroy(Request $request, UserSession $userSession): RedirectResponse
    {
        abort_unless($userSession->user_id === $request->user()->id, 403);

        $isCurrent = $this->userSessionManager->isCurrent($userSession, $request->session()->getId());

        $this->userSessionManager->revoke($userSession);

        if ($isCurrent) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('status', __('Your session has been revoked. Please sign in again.'));
        }

        return back()->with('status', __('The session has been revoked.'));
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $count = $this->userSessionManager->revokeAllExcept(
            $request->user(),
            $request->session()->getId(),
        );

        if ($count === 0) {
            return back()->with('status', __('No other active sessions to revoke.'));
        }

        return back()->with('status', trans_choice(
            '{1} :count other session has been revoked.|[2,*] :count other sessions have been revoked.',
            $count,
            ['count' => $count],
        ));
    }
}

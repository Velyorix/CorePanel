<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateNotificationPreferencesRequest;
use App\Models\User;
use Core\Notifications\Services\NotificationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationPreferencesController extends Controller
{
    public function __construct(
        private readonly NotificationPreferenceService $preferences,
    ) {
    }

    public function edit(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user?->can('client.account.view') ?? false, 403);

        return view('client.settings.notifications', [
            'preferences' => $this->preferences->forUser($user),
            'channels' => NotificationPreferenceService::CHANNELS,
            'categories' => NotificationPreferenceService::CATEGORIES,
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->preferences->update($user, $request->normalized());

        return redirect()
            ->route('client.settings.notifications')
            ->with('status', __('Notification preferences saved successfully.'));
    }
}

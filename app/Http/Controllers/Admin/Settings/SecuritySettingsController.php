<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Requests\Admin\UpdateSecuritySettingsRequest;
use Core\Permissions\Services\PermissionService;
use Core\Settings\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecuritySettingsController extends SettingsController
{
    public const TWO_FACTOR_REQUIRED = 'security.two_factor_required';

    public const PASSWORD_MIN_LENGTH = 'security.password_min_length';

    public const PASSWORD_REQUIRE_SPECIAL = 'security.password_require_special';

    public const PASSWORD_CHECK_COMPROMISED = 'security.password_check_compromised';

    public const SESSION_TIMEOUT_MINUTES = 'security.session_timeout_minutes';

    public function __construct(
        PermissionService $permissionService,
        private readonly SettingsService $settings,
    ) {
        parent::__construct($permissionService);
    }

    public function edit(Request $request): View
    {
        $this->ensureCanView($request);

        return view('admin.settings.security', [
            'values' => [
                'two_factor_required' => $this->settings->getBool(self::TWO_FACTOR_REQUIRED, false),
                'password_min_length' => $this->settings->getInt(
                    self::PASSWORD_MIN_LENGTH,
                    (int) config('corepanel.password.min_length', 12),
                ),
                'password_require_special' => $this->settings->getBool(
                    self::PASSWORD_REQUIRE_SPECIAL,
                    (bool) config('corepanel.password.require_special_character', true),
                ),
                'password_check_compromised' => $this->settings->getBool(
                    self::PASSWORD_CHECK_COMPROMISED,
                    (bool) config('corepanel.password.check_compromised', true),
                ),
                'session_timeout_minutes' => $this->settings->getInt(
                    self::SESSION_TIMEOUT_MINUTES,
                    (int) config('session.lifetime', 120),
                ),
            ],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function update(UpdateSecuritySettingsRequest $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $validated = $request->validated();

        $this->settings->set(self::TWO_FACTOR_REQUIRED, $request->boolean('two_factor_required'), 'boolean', true);
        $this->settings->set(self::PASSWORD_MIN_LENGTH, (int) $validated['password_min_length'], 'integer', true);
        $this->settings->set(
            self::PASSWORD_REQUIRE_SPECIAL,
            $request->boolean('password_require_special'),
            'boolean',
            true,
        );
        $this->settings->set(
            self::PASSWORD_CHECK_COMPROMISED,
            $request->boolean('password_check_compromised'),
            'boolean',
            true,
        );
        $this->settings->set(
            self::SESSION_TIMEOUT_MINUTES,
            (int) $validated['session_timeout_minutes'],
            'integer',
            true,
        );

        return redirect()
            ->route('admin.settings.security')
            ->with('status', __('Security settings saved successfully.'));
    }
}

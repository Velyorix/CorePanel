<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Requests\Admin\UpdateGeneralSettingsRequest;
use Core\Permissions\Services\PermissionService;
use Core\Settings\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GeneralSettingsController extends SettingsController
{
    public const SITE_NAME = 'general.site_name';

    public const LOCALE = 'general.locale';

    public const TIMEZONE = 'general.timezone';

    public function __construct(
        PermissionService $permissionService,
        private readonly SettingsService $settings,
    ) {
        parent::__construct($permissionService);
    }

    public function edit(Request $request): View
    {
        $this->ensureCanView($request);

        return view('admin.settings.general', [
            'values' => [
                'site_name' => $this->settings->getString(self::SITE_NAME, (string) config('corepanel.name', config('app.name'))),
                'locale' => $this->settings->getString(self::LOCALE, (string) config('app.locale', 'en')),
                'timezone' => $this->settings->getString(self::TIMEZONE, (string) config('app.timezone', 'UTC')),
            ],
            'locales' => (array) config('corepanel.locale.supported', ['en']),
            'timezones' => timezone_identifiers_list(),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function update(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $validated = $request->validated();

        $this->settings->set(self::SITE_NAME, $validated['site_name'], 'string', true);
        $this->settings->set(self::LOCALE, $validated['locale'], 'string', true);
        $this->settings->set(self::TIMEZONE, $validated['timezone'], 'string', true);

        return redirect()
            ->route('admin.settings.general')
            ->with('status', __('General settings saved successfully.'));
    }
}

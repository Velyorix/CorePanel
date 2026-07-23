<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Requests\Admin\UpdateMailSettingsRequest;
use Core\Permissions\Services\PermissionService;
use Core\Settings\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MailSettingsController extends SettingsController
{
    public const HOST = 'mail.host';

    public const PORT = 'mail.port';

    public const USERNAME = 'mail.username';

    public const PASSWORD = 'mail.password';

    public const ENCRYPTION = 'mail.encryption';

    public const FROM_ADDRESS = 'mail.from_address';

    public const FROM_NAME = 'mail.from_name';

    public function __construct(
        PermissionService $permissionService,
        private readonly SettingsService $settings,
    ) {
        parent::__construct($permissionService);
    }

    public function edit(Request $request): View
    {
        $this->ensureCanView($request);

        $passwordConfigured = filled($this->settings->getEncrypted(self::PASSWORD));

        return view('admin.settings.mail', [
            'values' => [
                'host' => $this->settings->getString(self::HOST, (string) config('mail.mailers.smtp.host', '')),
                'port' => $this->settings->getInt(self::PORT, (int) config('mail.mailers.smtp.port', 587)),
                'username' => $this->settings->getString(self::USERNAME, (string) config('mail.mailers.smtp.username', '')),
                'encryption' => $this->settings->getString(
                    self::ENCRYPTION,
                    (string) (config('mail.mailers.smtp.encryption') ?? 'tls'),
                ),
                'from_address' => $this->settings->getString(self::FROM_ADDRESS, (string) config('mail.from.address', '')),
                'from_name' => $this->settings->getString(self::FROM_NAME, (string) config('mail.from.name', '')),
            ],
            'passwordConfigured' => $passwordConfigured,
            'canManage' => $this->canManage($request),
        ]);
    }

    public function update(UpdateMailSettingsRequest $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $validated = $request->validated();

        $this->settings->set(self::HOST, $validated['host'], 'string', true);
        $this->settings->set(self::PORT, (int) $validated['port'], 'integer', true);
        $this->settings->set(self::USERNAME, $validated['username'] ?? '', 'string', true);
        $this->settings->set(self::ENCRYPTION, $validated['encryption'], 'string', true);
        $this->settings->set(self::FROM_ADDRESS, $validated['from_address'], 'string', true);
        $this->settings->set(self::FROM_NAME, $validated['from_name'], 'string', true);

        if (filled($validated['password'] ?? null)) {
            $this->settings->setEncrypted(self::PASSWORD, (string) $validated['password'], false);
        }

        return redirect()
            ->route('admin.settings.mail')
            ->with('status', __('Mail settings saved successfully.'));
    }
}

<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Http\Requests\Install\ActivateLicenseRequest;
use Core\License\Services\LicenseSettings;
use Core\License\Services\LicenseValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LicenseActivationController extends Controller
{
    public function __construct(
        private readonly LicenseSettings $licenseSettings,
        private readonly LicenseValidationService $licenseValidationService,
    ) {
    }

    public function create(): View|RedirectResponse
    {
        if ($this->licenseSettings->hasLicenseKey() && $this->licenseSettings->hasInstanceId()) {
            return redirect()
                ->route('login')
                ->with('status', __('License is already configured for this installation.'));
        }

        return view('install.license');
    }

    public function store(ActivateLicenseRequest $request): RedirectResponse
    {
        $instanceId = $this->licenseSettings->instanceId() ?? (string) Str::uuid();

        $this->licenseSettings->setLicenseKey((string) $request->validated('license_key'));
        $this->licenseSettings->setInstanceId($instanceId);

        $state = $this->licenseValidationService->validate(forceRefresh: true);

        if (! $state->isValid) {
            return back()
                ->withInput($request->except('license_key'))
                ->withErrors([
                    'license_key' => $state->message ?: __('Unable to activate this license key.'),
                ]);
        }

        return redirect()
            ->route('login')
            ->with('status', __('License activated successfully. You can now sign in.'));
    }
}

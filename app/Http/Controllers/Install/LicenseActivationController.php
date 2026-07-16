<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Http\Requests\Install\ActivateLicenseRequest;
use App\Models\User;
use Core\License\DataTransferObjects\LicenseValidationState;
use Core\License\Services\LicenseSettings;
use Core\License\Services\LicenseValidationService;
use Core\Permissions\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LicenseActivationController extends Controller
{
    public function __construct(
        private readonly LicenseSettings $licenseSettings,
        private readonly LicenseValidationService $licenseValidationService,
        private readonly PermissionService $permissionService,
    ) {
    }

    public function create(): View|RedirectResponse
    {
        // Allow re-entry when the license is configured but currently invalid / unreachable.
        if ($this->licenseSettings->hasLicenseKey() && $this->licenseSettings->hasInstanceId()) {
            $state = $this->licenseValidationService->validate(forceRefresh: true);

            if ($state->isValid) {
                return redirect()
                    ->to($this->configuredLicenseRedirectUrl())
                    ->with('status', __('License is already configured for this installation.'));
            }
        }

        return view('install.license');
    }

    public function store(ActivateLicenseRequest $request): RedirectResponse
    {
        $instanceId = $this->licenseSettings->instanceId() ?? (string) Str::uuid();
        $previousKey = $this->licenseSettings->licenseKey();

        $this->licenseSettings->setLicenseKey((string) $request->validated('license_key'));
        $this->licenseSettings->setInstanceId($instanceId);
        $this->licenseValidationService->forgetCache();

        $state = $this->licenseValidationService->validate(forceRefresh: true);

        if (! $state->isValid) {
            // Keep previous key when a re-entry attempt fails, if one existed.
            if (filled($previousKey) && $previousKey !== $request->validated('license_key')) {
                $this->licenseSettings->setLicenseKey((string) $previousKey);
            }

            return back()
                ->withInput($request->except('license_key'))
                ->withErrors([
                    'license_key' => $this->activationFailureMessage($state),
                ]);
        }

        return redirect()
            ->to($this->postActivationRedirectUrl())
            ->with('status', __('License activated successfully.'));
    }

    private function activationFailureMessage(LicenseValidationState $state): string
    {
        if (filled($state->message)) {
            return (string) $state->message;
        }

        return match ($state->reason) {
            'invalid_key' => __('The provided license key is invalid.'),
            'invalid_status' => __('This license cannot be used in its current status.'),
            'activation_limit_reached' => __('This license has reached its activation limit.'),
            'expired' => __('This license has expired.'),
            'server_error' => __('CorePanel.org is temporarily unavailable. Please try again later.'),
            'validation_failed' => __('The license activation request was rejected.'),
            'rate_limit_exceeded' => __('Too many license validation attempts. Please wait and try again.'),
            default => __('Unable to activate this license key.'),
        };
    }

    private function configuredLicenseRedirectUrl(): string
    {
        $user = Auth::user();

        if ($user instanceof User && $this->permissionService->userHasPermission($user, 'admin.access')) {
            return route('admin.dashboard');
        }

        return route('login');
    }

    private function postActivationRedirectUrl(): string
    {
        return $this->configuredLicenseRedirectUrl();
    }
}

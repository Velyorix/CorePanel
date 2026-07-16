<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLicenseKeyRequest;
use Core\License\Models\LicenseActivation;
use Core\License\Services\EntitlementService;
use Core\License\Services\LicenseSettings;
use Core\License\Services\LicenseValidationService;
use Core\Permissions\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LicenseController extends Controller
{
    public function __construct(
        private readonly LicenseSettings $licenseSettings,
        private readonly LicenseValidationService $licenseValidationService,
        private readonly EntitlementService $entitlementService,
        private readonly PermissionService $permissionService,
    ) {
    }

    public function show(Request $request): View
    {
        $this->ensureCanView($request);

        $state = $this->licenseValidationService->validate();
        $instanceId = $this->licenseSettings->instanceId();

        $activation = filled($instanceId)
            ? LicenseActivation::query()->where('instance_id', $instanceId)->first()
            : null;

        return view('admin.license.show', [
            'state' => $state,
            'activation' => $activation,
            'maskedLicenseKey' => $this->licenseSettings->maskedLicenseKey(),
            'instanceId' => $instanceId,
            'entitlements' => $this->entitlementService->all(),
            'modules' => $this->entitlementService->modules(),
            'themes' => $this->entitlementService->themes(),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function update(UpdateLicenseKeyRequest $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $instanceId = $this->licenseSettings->instanceId();

        if (blank($instanceId)) {
            return redirect()
                ->route('install.license.create')
                ->withErrors([
                    'license_key' => __('Instance id is missing. Complete installation first.'),
                ]);
        }

        $previousKey = $this->licenseSettings->licenseKey();

        $this->licenseSettings->setLicenseKey((string) $request->validated('license_key'));
        $this->licenseValidationService->forgetCache();

        $state = $this->licenseValidationService->validate(forceRefresh: true);

        if (! $state->isValid) {
            if (filled($previousKey)) {
                $this->licenseSettings->setLicenseKey((string) $previousKey);
                $this->licenseValidationService->forgetCache();
            }

            return back()
                ->withInput($request->except('license_key'))
                ->withErrors([
                    'license_key' => $state->message ?: __('Unable to update this license key.'),
                ]);
        }

        return redirect()
            ->route('admin.license.show')
            ->with('status', __('License key updated and validated successfully.'));
    }

    public function revalidate(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $this->licenseValidationService->forgetCache();
        $state = $this->licenseValidationService->validate(forceRefresh: true);

        if (! $state->isValid) {
            return redirect()
                ->route('admin.license.show')
                ->with('error', $state->message ?: __('License revalidation failed.'));
        }

        $message = $state->inGracePeriod
            ? __('License is running in grace mode. Last successful validation is still within the grace window.')
            : __('License revalidated successfully.');

        return redirect()
            ->route('admin.license.show')
            ->with('status', $message);
    }

    private function ensureCanView(Request $request): void
    {
        $user = $request->user();

        if ($user === null || ! $this->permissionService->userHasPermission($user, 'settings.view')) {
            abort(403, __('You do not have permission to view license settings.'));
        }
    }

    private function ensureCanManage(Request $request): void
    {
        if (! $this->canManage($request)) {
            abort(403, __('You do not have permission to manage license settings.'));
        }
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && $this->permissionService->userHasPermission($user, 'settings.manage');
    }
}

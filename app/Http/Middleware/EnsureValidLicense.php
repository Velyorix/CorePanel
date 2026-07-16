<?php

namespace App\Http\Middleware;

use Closure;
use Core\License\DataTransferObjects\LicenseValidationState;
use Core\License\Services\LicenseValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidLicense
{
    public function __construct(
        private readonly LicenseValidationService $licenseValidationService,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isExcepted($request)) {
            return $next($request);
        }

        $state = $this->licenseValidationService->validate();

        // Stale transport failures (e.g. fixed local SSL) should not trap the UI for the full TTL.
        if (! $state->isValid && $state->reason === 'request_failed' && $state->source === 'cache') {
            $this->licenseValidationService->forgetCache();
            $state = $this->licenseValidationService->validate(forceRefresh: true);
        }

        $request->attributes->set('license.validation_state', $state);

        if ($state->status === 'missing_configuration') {
            return $this->redirectToInstallWizard($request);
        }

        if ($state->isValid) {
            if ($state->inGracePeriod) {
                View::share('licenseDegraded', true);
                View::share('licenseValidationState', $state);
            }

            return $next($request);
        }

        return $this->blockedResponse($request, $state);
    }

    private function isExcepted(Request $request): bool
    {
        $patterns = config('corepanel.license.except', []);

        foreach ($patterns as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function redirectToInstallWizard(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => [
                    'code' => 'license_not_configured',
                    'message' => __('License is not configured for this installation.'),
                ],
            ], 503);
        }

        return Redirect::guest(route('install.license.create'));
    }

    private function blockedResponse(Request $request, LicenseValidationState $state): Response
    {
        $message = $state->message ?: __('This installation does not have a valid license.');

        if ($request->expectsJson()) {
            return response()->json([
                'error' => [
                    'code' => 'license_invalid',
                    'message' => $message,
                    'reason' => $state->reason,
                    'status' => $state->status,
                ],
            ], 503);
        }

        return response()->view('license.degraded', [
            'message' => $message,
            'state' => $state,
        ], 503);
    }
}

<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use Core\Permissions\Services\PermissionService;
use Illuminate\Http\Request;

abstract class SettingsController extends Controller
{
    public function __construct(
        protected readonly PermissionService $permissionService,
    ) {
    }

    protected function ensureCanView(Request $request): void
    {
        $user = $request->user();

        if ($user === null || ! $this->permissionService->userHasPermission($user, 'settings.view')) {
            abort(403, __('You do not have permission to view settings.'));
        }
    }

    protected function ensureCanManage(Request $request): void
    {
        if (! $this->canManage($request)) {
            abort(403, __('You do not have permission to manage settings.'));
        }
    }

    protected function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && $this->permissionService->userHasPermission($user, 'settings.manage');
    }
}

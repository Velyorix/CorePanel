<?php

namespace App\Http\Middleware;

use Closure;
use Core\Auth\Models\User;
use Core\Permissions\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if ($permissions === []) {
            abort(500, 'At least one permission must be specified.');
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $this->unauthenticated($request);
        }

        if (! $this->permissionService->userHasAllPermissions($user, $permissions)) {
            return $this->forbidden($request);
        }

        return $next($request);
    }

    private function unauthenticated(Request $request): Response
    {
        if ($request->expectsJson()) {
            abort(401, __('Unauthenticated.'));
        }

        return Redirect::guest(route('login'));
    }

    private function forbidden(Request $request): Response
    {
        if ($request->expectsJson()) {
            abort(403, __('You do not have permission to perform this action.'));
        }

        abort(403, __('You do not have permission to perform this action.'));
    }
}

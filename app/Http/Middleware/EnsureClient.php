<?php

namespace App\Http\Middleware;

use Closure;
use Core\Auth\Models\User;
use Core\Permissions\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

class EnsureClient
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->unauthenticated($request);
        }

        if (! $this->permissionService->userHasPermission($user, 'client.access')) {
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
            abort(403, __('You do not have access to the client area.'));
        }

        abort(403, __('You do not have access to the client area.'));
    }
}

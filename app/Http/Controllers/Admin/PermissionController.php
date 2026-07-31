<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Permissions\Models\Permission;
use Core\Permissions\Models\Role;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        $permissions = Permission::query()
            ->withCount('roles')
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => $permission->module ?: 'general');

        return view('admin.permissions.index', compact('permissions'));
    }
}

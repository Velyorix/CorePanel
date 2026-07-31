<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DuplicateRoleRequest;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\User;
use Core\Permissions\Models\Permission;
use Core\Permissions\Models\Role;
use Core\Permissions\Services\RoleManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleManagementService $roleManagementService,
    ) {
    }

    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::query()
            ->with(['parent', 'permissions'])
            ->withCount(['users', 'children'])
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create(): View
    {
        Gate::authorize('create', Role::class);

        return view('admin.roles.create', $this->formData());
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        try {
            $role = $this->roleManagementService->create($request->roleData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['parent_id' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.roles.show', $role)
            ->with('status', __('Role created successfully.'));
    }

    public function show(Role $role): View
    {
        Gate::authorize('view', $role);

        $role->load(['permissions', 'users', 'parent', 'children']);

        return view('admin.roles.show', compact('role'));
    }

    public function edit(Role $role): View
    {
        Gate::authorize('update', $role);

        $role->load(['permissions', 'users']);

        return view('admin.roles.edit', array_merge($this->formData($role), [
            'role' => $role,
        ]));
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        try {
            $role = $this->roleManagementService->update($role, $request->roleData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['parent_id' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.roles.show', $role)
            ->with('status', __('Role updated successfully.'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        try {
            $this->roleManagementService->delete($role);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['role' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('status', __('Role deleted successfully.'));
    }

    public function duplicate(DuplicateRoleRequest $request, Role $role): RedirectResponse
    {
        try {
            $duplicate = $this->roleManagementService->duplicate($role, $request->name());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['name' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.roles.edit', $duplicate)
            ->with('status', __('Role duplicated successfully.'));
    }

    /**
     * @return array{permissions: \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Permission>>, parentRoles: \Illuminate\Database\Eloquent\Collection<int, Role>, users: \Illuminate\Database\Eloquent\Collection<int, User>}
     */
    private function formData(?Role $current = null): array
    {
        $parentRoles = Role::query()
            ->orderBy('name')
            ->when($current !== null, fn ($query) => $query->whereKeyNot($current->id))
            ->get();

        return [
            'permissions' => Permission::query()
                ->orderBy('module')
                ->orderBy('name')
                ->get()
                ->groupBy(fn (Permission $permission): string => $permission->module ?: 'general'),
            'parentRoles' => $parentRoles,
            'users' => User::query()->orderBy('email')->get(['id', 'name', 'email']),
        ];
    }
}

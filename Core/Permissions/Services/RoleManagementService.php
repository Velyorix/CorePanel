<?php

namespace Core\Permissions\Services;

use Core\Permissions\DataTransferObjects\RoleData;
use Core\Permissions\Models\Permission;
use Core\Permissions\Models\Role;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class RoleManagementService
{
    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly RoleInheritanceService $roleInheritanceService,
    ) {
    }

    public function create(RoleData $data): Role
    {
        $this->assertParentIsValid(null, $data->parentId);
        $this->assertPermissionIdsExist($data->permissionIds);

        return DB::transaction(function () use ($data): Role {
            $role = Role::query()->create([
                'name' => $data->name,
                'description' => $data->description,
                'parent_id' => $data->parentId,
                'is_system' => false,
            ]);

            $role->permissions()->sync($data->permissionIds);
            $role->users()->sync($data->userIds);

            $this->invalidateAccessForRole($role);

            return $role->fresh(['permissions', 'users', 'parent']);
        });
    }

    public function update(Role $role, RoleData $data): Role
    {
        $this->assertParentIsValid($role, $data->parentId);
        $this->assertPermissionIdsExist($data->permissionIds);

        return DB::transaction(function () use ($role, $data): Role {
            $affectedUserIds = $role->users()->pluck('users.id');

            $role->update([
                'name' => $role->is_system ? $role->name : $data->name,
                'description' => $data->description,
                'parent_id' => $data->parentId,
            ]);

            $role->permissions()->sync($data->permissionIds);
            $role->users()->sync($data->userIds);

            $affectedUserIds = $affectedUserIds
                ->merge($role->users()->pluck('users.id'))
                ->unique()
                ->values();

            foreach ($affectedUserIds as $userId) {
                $this->permissionService->forgetUser((int) $userId);
            }

            $this->permissionService->forgetRole($role->fresh());

            return $role->fresh(['permissions', 'users', 'parent']);
        });
    }

    public function delete(Role $role): void
    {
        if ($role->is_system) {
            throw new RuntimeException('System roles cannot be deleted.');
        }

        if ($role->children()->exists()) {
            throw new RuntimeException('Cannot delete a role that still has child roles.');
        }

        DB::transaction(function () use ($role): void {
            $this->invalidateAccessForRole($role);

            $role->permissions()->detach();
            $role->users()->detach();
            $role->delete();
        });
    }

    public function duplicate(Role $role, string $name): Role
    {
        $role->loadMissing(['permissions', 'users']);

        return $this->create(new RoleData(
            name: $name,
            description: $role->description,
            parentId: $role->parent_id,
            permissionIds: $role->permissions->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            userIds: [],
        ));
    }

    private function assertParentIsValid(?Role $role, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $parent = Role::query()->find($parentId);

        if ($parent === null) {
            throw new InvalidArgumentException('The selected parent role does not exist.');
        }

        if ($role !== null && $this->roleInheritanceService->wouldCreateCycle($role, $parentId)) {
            throw new InvalidArgumentException('The selected parent role would create an inheritance cycle.');
        }
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function assertPermissionIdsExist(array $permissionIds): void
    {
        if ($permissionIds === []) {
            return;
        }

        $existingCount = Permission::query()->whereIn('id', $permissionIds)->count();

        if ($existingCount !== count(array_unique($permissionIds))) {
            throw new InvalidArgumentException('One or more selected permissions are invalid.');
        }
    }

    private function invalidateAccessForRole(Role $role): void
    {
        $this->permissionService->forgetRole($role);
    }
}

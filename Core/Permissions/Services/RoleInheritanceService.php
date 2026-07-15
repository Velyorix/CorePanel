<?php

namespace Core\Permissions\Services;

use Core\Permissions\Models\Role;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class RoleInheritanceService
{
    /**
     * @return list<string>
     */
    public function permissionsForRole(Role $role): array
    {
        if (! $this->isEnabled()) {
            return $this->directPermissionsForRole($role);
        }

        return $this->collectPermissionsForRole($role, collect())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function directPermissionsForRole(Role $role): array
    {
        $role->loadMissing('permissions');

        return $role->permissions
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @return EloquentCollection<int, Role>
     */
    public function ancestorRoles(Role $role): EloquentCollection
    {
        $ancestors = new EloquentCollection;
        $visited = [];
        $current = $role->parent;

        while ($current !== null) {
            if (in_array($current->getKey(), $visited, true)) {
                break;
            }

            $visited[] = $current->getKey();
            $ancestors->push($current);
            $current->loadMissing('parent');
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * @return EloquentCollection<int, Role>
     */
    public function descendantRoles(Role $role): EloquentCollection
    {
        $role->loadMissing('children');

        $descendants = new EloquentCollection;

        foreach ($role->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($this->descendantRoles($child));
        }

        return $descendants;
    }

    public function wouldCreateCycle(Role $role, ?int $parentId): bool
    {
        if ($parentId === null || $parentId === $role->getKey()) {
            return $parentId === $role->getKey();
        }

        $parent = Role::query()->find($parentId);

        if ($parent === null) {
            return false;
        }

        return $this->ancestorRoles($parent)->contains(
            fn (Role $ancestor): bool => $ancestor->is($role),
        ) || $parent->is($role);
    }

    /**
     * @param  Collection<int, int>  $visited
     * @return Collection<int, string>
     */
    private function collectPermissionsForRole(Role $role, Collection $visited): Collection
    {
        if ($visited->contains($role->getKey())) {
            return collect();
        }

        $visited->push($role->getKey());

        $role->loadMissing(['permissions', 'parent']);

        $permissions = $role->permissions->pluck('name');

        if ($role->parent !== null) {
            $permissions = $permissions->merge(
                $this->collectPermissionsForRole($role->parent, $visited),
            );
        }

        return $permissions;
    }

    private function isEnabled(): bool
    {
        return (bool) config('corepanel.rbac.inheritance.enabled', true);
    }
}

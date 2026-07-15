<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Permissions\Models\Role;

class RolePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->allowsAny($user, 'roles.view');
    }

    public function create(User $user): bool
    {
        return $this->allowsAny($user, 'roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->allowsAny($user, 'roles.manage');
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->allowsAny($user, 'roles.manage') && ! $role->is_system;
    }
}

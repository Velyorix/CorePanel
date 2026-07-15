<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;

class UserPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $this->allows($user, 'users.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->allowsAny($user, 'users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $this->allows($user, 'users.update', $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->allows($user, 'users.delete', $model);
    }
}

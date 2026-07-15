<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;

class UserPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $this->allows($user, 'users.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $this->allows($user, 'users.update');
    }

    public function delete(User $user, User $model): bool
    {
        return $this->allows($user, 'users.delete');
    }
}

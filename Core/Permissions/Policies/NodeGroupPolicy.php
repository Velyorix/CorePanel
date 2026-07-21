<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Nodes\Models\NodeGroup;

class NodeGroupPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'nodes.view');
    }

    public function view(User $user, NodeGroup $group): bool
    {
        return $this->allows($user, 'nodes.view', $group);
    }

    public function create(User $user): bool
    {
        return $this->allowsAny($user, 'nodes.manage');
    }

    public function update(User $user, NodeGroup $group): bool
    {
        return $this->allows($user, 'nodes.manage', $group);
    }

    public function delete(User $user, NodeGroup $group): bool
    {
        return $this->allows($user, 'nodes.manage', $group);
    }
}

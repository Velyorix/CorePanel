<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Nodes\Models\NodeCluster;

class NodeClusterPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'nodes.view');
    }

    public function view(User $user, NodeCluster $cluster): bool
    {
        return $this->allows($user, 'nodes.view', $cluster);
    }

    public function create(User $user): bool
    {
        return $this->allowsAny($user, 'nodes.manage');
    }

    public function update(User $user, NodeCluster $cluster): bool
    {
        return $this->allows($user, 'nodes.manage', $cluster);
    }

    public function delete(User $user, NodeCluster $cluster): bool
    {
        return $this->allows($user, 'nodes.manage', $cluster);
    }
}

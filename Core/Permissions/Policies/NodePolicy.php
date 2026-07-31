<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Nodes\Models\Node;

class NodePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'nodes.view');
    }

    public function view(User $user, Node $node): bool
    {
        return $this->allows($user, 'nodes.view', $node);
    }

    public function create(User $user): bool
    {
        return $this->allowsAny($user, 'nodes.manage');
    }

    public function update(User $user, Node $node): bool
    {
        return $this->allows($user, 'nodes.manage', $node);
    }

    public function delete(User $user, Node $node): bool
    {
        return $this->allows($user, 'nodes.manage', $node);
    }
}

<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Automation\Models\Workflow;

class WorkflowPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'automation.view');
    }

    public function view(User $user, Workflow $workflow): bool
    {
        return $this->allows($user, 'automation.view', $workflow);
    }

    public function create(User $user): bool
    {
        return $this->allowsAny($user, 'automation.manage');
    }

    public function update(User $user, Workflow $workflow): bool
    {
        return $this->allows($user, 'automation.manage', $workflow);
    }

    public function delete(User $user, Workflow $workflow): bool
    {
        return $this->allows($user, 'automation.manage', $workflow);
    }
}

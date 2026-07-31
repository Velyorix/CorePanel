<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Automation\Models\AutomationRule;

class AutomationRulePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'automation.view');
    }

    public function view(User $user, AutomationRule $rule): bool
    {
        return $this->allows($user, 'automation.view', $rule);
    }

    public function create(User $user): bool
    {
        return $this->allowsAny($user, 'automation.manage');
    }

    public function update(User $user, AutomationRule $rule): bool
    {
        return $this->allows($user, 'automation.manage', $rule);
    }

    public function delete(User $user, AutomationRule $rule): bool
    {
        return $this->allows($user, 'automation.manage', $rule);
    }
}

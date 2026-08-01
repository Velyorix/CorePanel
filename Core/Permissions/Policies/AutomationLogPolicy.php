<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Automation\Models\AutomationLog;

class AutomationLogPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'automation.view');
    }

    public function view(User $user, AutomationLog $log): bool
    {
        return $this->allows($user, 'automation.view', $log);
    }

    public function retry(User $user, AutomationLog $log): bool
    {
        return $this->allows($user, 'automation.manage', $log);
    }
}

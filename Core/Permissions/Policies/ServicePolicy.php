<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Services\Models\Service;

class ServicePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'services.view');
    }

    public function view(User $user, Service $service): bool
    {
        return $this->allows($user, 'services.view', $service);
    }

    public function manage(User $user, ?Service $service = null): bool
    {
        return $service === null
            ? $this->allowsAny($user, 'services.manage')
            : $this->allows($user, 'services.manage', $service);
    }
}

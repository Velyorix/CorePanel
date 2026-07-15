<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Clients\Models\Client;

class ClientPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'clients.view');
    }

    public function view(User $user, Client $client): bool
    {
        return $this->allows($user, 'clients.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'clients.create');
    }

    public function update(User $user, Client $client): bool
    {
        return $this->allows($user, 'clients.update');
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->allows($user, 'clients.delete');
    }

    public function impersonate(User $user, Client $client): bool
    {
        return $this->allows($user, 'clients.impersonate');
    }
}

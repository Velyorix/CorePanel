<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Clients\Models\Client;

class ClientPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'clients.view');
    }

    public function view(User $user, Client $client): bool
    {
        return $this->allows($user, 'clients.view', $client);
    }

    public function create(User $user): bool
    {
        return $this->allowsAny($user, 'clients.create');
    }

    public function update(User $user, Client $client): bool
    {
        return $this->allows($user, 'clients.update', $client);
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->allows($user, 'clients.delete', $client);
    }

    public function impersonate(User $user, Client $client): bool
    {
        return $this->allowsAny($user, 'clients.impersonate');
    }
}

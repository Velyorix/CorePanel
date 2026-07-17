<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Orders\Models\Order;

class OrderPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $this->allows($user, 'orders.view', $order);
    }

    public function manage(User $user, ?Order $order = null): bool
    {
        return $order === null
            ? $this->allowsAny($user, 'orders.manage')
            : $this->allows($user, 'orders.manage', $order);
    }
}

<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Products\Models\Product;

class ProductPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->allows($user, 'products.view', $product);
    }

    public function create(User $user): bool
    {
        return $this->allowsAny($user, 'products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->allows($user, 'products.update', $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->allows($user, 'products.delete', $product);
    }
}

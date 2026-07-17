<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Products\Models\ProductCategory;

class ProductCategoryPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'products.view');
    }

    public function view(User $user, ProductCategory $category): bool
    {
        return $this->allows($user, 'products.view', $category);
    }

    public function create(User $user): bool
    {
        return $this->allowsAny($user, 'products.create');
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return $this->allows($user, 'products.update', $category);
    }

    public function delete(User $user, ProductCategory $category): bool
    {
        return $this->allows($user, 'products.delete', $category);
    }
}

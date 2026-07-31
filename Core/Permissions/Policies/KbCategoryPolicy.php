<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\KnowledgeBase\Models\KbCategory;

class KbCategoryPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'kb.view');
    }

    public function view(User $user, KbCategory $kbCategory): bool
    {
        return $this->allows($user, 'kb.view', $kbCategory);
    }

    public function create(User $user): bool
    {
        return $this->allowsAny($user, 'kb.manage');
    }

    public function update(User $user, KbCategory $kbCategory): bool
    {
        return $this->allows($user, 'kb.manage', $kbCategory);
    }

    public function delete(User $user, KbCategory $kbCategory): bool
    {
        return $this->allows($user, 'kb.manage', $kbCategory);
    }
}

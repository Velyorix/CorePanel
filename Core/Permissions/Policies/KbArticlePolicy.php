<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\KnowledgeBase\Models\KbArticle;

class KbArticlePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'kb.view');
    }

    public function view(User $user, KbArticle $kbArticle): bool
    {
        return $this->allows($user, 'kb.view', $kbArticle);
    }

    public function create(User $user): bool
    {
        return $this->allowsAny($user, 'kb.manage');
    }

    public function update(User $user, KbArticle $kbArticle): bool
    {
        return $this->allows($user, 'kb.manage', $kbArticle);
    }

    public function delete(User $user, KbArticle $kbArticle): bool
    {
        return $this->allows($user, 'kb.manage', $kbArticle);
    }
}

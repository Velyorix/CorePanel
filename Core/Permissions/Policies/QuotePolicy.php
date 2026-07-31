<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Billing\Models\Quote;

class QuotePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'billing.quotes.view');
    }

    public function view(User $user, Quote $quote): bool
    {
        return $this->allows($user, 'billing.quotes.view', $quote);
    }

    public function manage(User $user, ?Quote $quote = null): bool
    {
        return $quote === null
            ? $this->allowsAny($user, 'billing.quotes.manage')
            : $this->allows($user, 'billing.quotes.manage', $quote);
    }
}

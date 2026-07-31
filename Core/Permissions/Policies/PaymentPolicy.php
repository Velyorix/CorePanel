<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Billing\Models\Payment;

class PaymentPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'billing.payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->allows($user, 'billing.payments.view', $payment);
    }

    public function manage(User $user, ?Payment $payment = null): bool
    {
        return $payment === null
            ? $this->allowsAny($user, 'billing.payments.manage')
            : $this->allows($user, 'billing.payments.manage', $payment);
    }
}

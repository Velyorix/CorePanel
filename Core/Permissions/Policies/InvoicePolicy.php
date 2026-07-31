<?php

namespace Core\Permissions\Policies;

use Core\Auth\Models\User;
use Core\Billing\Models\Invoice;

class InvoicePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, 'billing.invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->allows($user, 'billing.invoices.view', $invoice);
    }

    public function manage(User $user, ?Invoice $invoice = null): bool
    {
        return $invoice === null
            ? $this->allowsAny($user, 'billing.invoices.manage')
            : $this->allows($user, 'billing.invoices.manage', $invoice);
    }
}

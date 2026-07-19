<?php

namespace Core\Billing\Events;

use Core\Billing\Models\CreditNote;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a draft credit note is issued.
 */
class CreditNoteIssued implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CreditNote $creditNote,
    ) {
    }
}

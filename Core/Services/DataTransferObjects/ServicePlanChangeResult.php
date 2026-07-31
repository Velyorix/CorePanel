<?php

namespace Core\Services\DataTransferObjects;

use Core\Billing\DataTransferObjects\ProrataResult;
use Core\Billing\Models\ClientCreditTransaction;
use Core\Billing\Models\Invoice;
use Core\Services\Enums\ServiceAction;
use Core\Services\Models\Service;

final readonly class ServicePlanChangeResult
{
    public function __construct(
        public Service $service,
        public ServiceAction $action,
        public ProrataResult $prorata,
        public bool $applied,
        public ?Invoice $chargeInvoice = null,
        public ?ClientCreditTransaction $creditTransaction = null,
    ) {
    }
}

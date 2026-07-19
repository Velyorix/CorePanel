<?php

namespace Core\Services\Exceptions;

use Core\Services\Enums\ServiceAction;
use RuntimeException;

class ModuleActionFailedException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        public readonly ServiceAction $action,
        public readonly array $response,
        public readonly int $serviceId,
    ) {
        parent::__construct(sprintf(
            'Module action [%s] failed for service #%d.',
            $action->value,
            $serviceId,
        ));
    }
}

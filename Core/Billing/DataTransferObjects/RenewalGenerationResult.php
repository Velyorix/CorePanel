<?php

namespace Core\Billing\DataTransferObjects;

final readonly class RenewalGenerationResult
{
    public function __construct(
        public int $created = 0,
        public int $skipped = 0,
        public int $errors = 0,
    ) {
    }

    public function withCreated(): self
    {
        return new self($this->created + 1, $this->skipped, $this->errors);
    }

    public function withSkipped(): self
    {
        return new self($this->created, $this->skipped + 1, $this->errors);
    }

    public function withError(): self
    {
        return new self($this->created, $this->skipped, $this->errors + 1);
    }
}

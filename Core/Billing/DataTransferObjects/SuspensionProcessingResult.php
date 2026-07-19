<?php

namespace Core\Billing\DataTransferObjects;

final readonly class SuspensionProcessingResult
{
    public function __construct(
        public int $suspended = 0,
        public int $terminated = 0,
        public int $skipped = 0,
        public int $errors = 0,
    ) {
    }

    public function withSuspended(): self
    {
        return new self($this->suspended + 1, $this->terminated, $this->skipped, $this->errors);
    }

    public function withTerminated(): self
    {
        return new self($this->suspended, $this->terminated + 1, $this->skipped, $this->errors);
    }

    public function withSkipped(): self
    {
        return new self($this->suspended, $this->terminated, $this->skipped + 1, $this->errors);
    }

    public function withError(): self
    {
        return new self($this->suspended, $this->terminated, $this->skipped, $this->errors + 1);
    }
}

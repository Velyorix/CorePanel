<?php

namespace Core\Billing\DataTransferObjects;

final readonly class ReminderProcessingResult
{
    public function __construct(
        public int $markedOverdue = 0,
        public int $sent = 0,
        public int $skipped = 0,
        public int $errors = 0,
    ) {
    }

    public function withMarkedOverdue(int $count = 1): self
    {
        return new self($this->markedOverdue + $count, $this->sent, $this->skipped, $this->errors);
    }

    public function withSent(): self
    {
        return new self($this->markedOverdue, $this->sent + 1, $this->skipped, $this->errors);
    }

    public function withSkipped(): self
    {
        return new self($this->markedOverdue, $this->sent, $this->skipped + 1, $this->errors);
    }

    public function withError(): self
    {
        return new self($this->markedOverdue, $this->sent, $this->skipped, $this->errors + 1);
    }
}

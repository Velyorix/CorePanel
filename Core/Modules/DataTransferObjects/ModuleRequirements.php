<?php

namespace Core\Modules\DataTransferObjects;

final readonly class ModuleRequirements
{
    public function __construct(
        public ?string $corepanel = null,
        public ?string $php = null,
    ) {
    }

    /**
     * @param  array{corepanel?: mixed, php?: mixed}|null  $data
     */
    public static function fromArray(?array $data): self
    {
        if ($data === null) {
            return new self;
        }

        $corepanel = isset($data['corepanel']) ? trim((string) $data['corepanel']) : null;
        $php = isset($data['php']) ? trim((string) $data['php']) : null;

        return new self(
            corepanel: $corepanel !== '' ? $corepanel : null,
            php: $php !== '' ? $php : null,
        );
    }

    public function isEmpty(): bool
    {
        return $this->corepanel === null && $this->php === null;
    }

    /**
     * @return array{corepanel?: string, php?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'corepanel' => $this->corepanel,
            'php' => $this->php,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}

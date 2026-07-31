<?php

namespace Core\Modules\DataTransferObjects;

final readonly class ModuleAuthor
{
    public function __construct(
        public string $name,
        public ?string $email = null,
        public ?string $homepage = null,
    ) {
    }

    /**
     * @param  array{name?: mixed, email?: mixed, homepage?: mixed}|string  $entry
     */
    public static function fromManifestEntry(array|string $entry): self
    {
        if (is_string($entry)) {
            $name = trim($entry);

            return new self(name: $name !== '' ? $name : 'Unknown');
        }

        $name = trim((string) ($entry['name'] ?? ''));

        return new self(
            name: $name !== '' ? $name : 'Unknown',
            email: isset($entry['email']) ? trim((string) $entry['email']) ?: null : null,
            homepage: isset($entry['homepage']) ? trim((string) $entry['homepage']) ?: null : null,
        );
    }

    /**
     * @return array{name: string, email?: string, homepage?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'homepage' => $this->homepage,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}

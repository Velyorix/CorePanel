<?php

namespace Core\Providers\DataTransferObjects;

use Core\Providers\Exceptions\InvalidModulePermissionException;
use Illuminate\Support\Str;

final readonly class ModulePermissionDefinition
{
    public function __construct(
        public string $name,
        public ?string $description = null,
    ) {
    }

    /**
     * @param  array{name?: string, description?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw InvalidModulePermissionException::invalidManifestEntry($data);
        }

        $description = isset($data['description']) ? trim((string) $data['description']) : null;

        return new self(
            $name,
            $description === '' ? null : $description,
        );
    }

    public static function fromManifestEntry(string|array $entry): self
    {
        if (is_string($entry)) {
            $name = trim($entry);

            if ($name === '') {
                throw InvalidModulePermissionException::invalidManifestEntry($entry);
            }

            return new self($name);
        }

        if (! is_array($entry)) {
            throw InvalidModulePermissionException::invalidManifestEntry($entry);
        }

        return self::fromArray($entry);
    }

    public function descriptionOrDefault(string $moduleKey): string
    {
        if ($this->description !== null) {
            return $this->description;
        }

        $segments = explode('.', $this->name);
        $action = (string) ($segments[array_key_last($segments)] ?? 'access');
        $resource = (string) ($segments[count($segments) - 2] ?? 'resource');

        return Str::headline($action).' '.$resource.' ('.$moduleKey.')';
    }
}
